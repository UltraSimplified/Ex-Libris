<?php

namespace App\Support;

use App\Models\Festival;
use App\Models\Issue;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\ShopCategory;
use App\Models\ShopSettings;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What the site's own pages - the home page, the listings, the magazine's
 * indexes, a venue, a promoter - tell Facebook, X, WhatsApp and search
 * engines about themselves, where they have no record of their own to
 * speak for them.
 *
 * A page that sends no meta at all is given the site's: its title, the
 * description below, and the latest issue's cover, so no link to the site
 * is ever shared bare.
 */
class SiteMeta
{
    /**
     * In the magazine's own words, from Who we are.
     */
    public const DESCRIPTION = 'Good on Paper, your friendly neighbourhood magazine: a local arts and listings mag, in print and online.';

    private static ?string $defaultImage = null;

    private static bool $defaultImageLooked = false;

    /**
     * An ordinary page: its title, and the site's description and picture
     * unless it has better of its own.
     */
    public static function for(string $title, ?string $description = null, ?string $image = null, ?string $url = null): PageMeta
    {
        $description = filled($description) ? $description : self::DESCRIPTION;
        $url ??= url()->current();

        return new PageMeta(
            title: $title,
            description: $description,
            image: $image ?? self::defaultImage(),
            url: $url,
            type: 'website',
            jsonLd: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'description' => $description,
                'url' => $url,
                'isPartOf' => self::website(),
            ]),
        );
    }

    /**
     * The home page, which speaks for the site as a whole.
     */
    public static function home(): PageMeta
    {
        return new PageMeta(
            title: FeatureMeta::PUBLISHER,
            description: self::DESCRIPTION,
            image: self::defaultImage(),
            url: url('/'),
            type: 'website',
            jsonLd: [
                ...self::website(),
                '@context' => 'https://schema.org',
                'description' => self::DESCRIPTION,
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => FeatureMeta::PUBLISHER,
                    'url' => url('/'),
                ],
            ],
        );
    }

    /**
     * A venue's page of listings: its picture, what the Office has written
     * about it, and a Place record with its address and where it is.
     */
    public static function venue(Venue $venue): PageMeta
    {
        $written = (string) Str::of((string) $venue->description)
            ->replaceMatches('#</(p|h[1-6]|li|div)>|<br\s*/?>#i', ' ')
            ->stripTags()
            ->squish();

        $where = collect([$venue->address, $venue->town])->filter()->implode(', ');
        $description = $written !== ''
            ? Str::limit($written, 200)
            : "What's on at {$venue->name}".($where !== '' ? ", {$where}" : '').', listed by Good on Paper.';

        $image = filled($venue->cover_photo) ? Storage::disk('do')->url($venue->cover_photo) : null;
        $url = url()->current();

        return new PageMeta(
            title: $venue->name,
            description: $description,
            image: $image ?? self::defaultImage(),
            url: $url,
            type: 'website',
            jsonLd: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Place',
                'name' => $venue->name,
                'description' => $description,
                'url' => $url,
                'image' => $image,
                'sameAs' => filled($venue->url) ? $venue->url : null,
                'address' => array_filter([
                    '@type' => 'PostalAddress',
                    'streetAddress' => $venue->address,
                    'addressLocality' => $venue->town,
                    'postalCode' => $venue->postcode,
                    'addressCountry' => 'GB',
                ]),
                'geo' => $venue->latitude !== null && $venue->longitude !== null ? [
                    '@type' => 'GeoCoordinates',
                    'latitude' => $venue->latitude,
                    'longitude' => $venue->longitude,
                ] : null,
            ]),
        );
    }

    /**
     * A promoter's page of listings: its banner, or its logo where it has
     * no banner, what it says about itself, and an Organization record.
     */
    public static function promoter(Promoter $promoter): PageMeta
    {
        ['description' => $description, 'image' => $image] = self::profile($promoter, "What's on from {$promoter->name}, listed by Good on Paper.");
        $url = url()->current();

        return new PageMeta(
            title: $promoter->name,
            description: $description,
            image: $image ?? self::defaultImage(),
            url: $url,
            type: 'website',
            jsonLd: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $promoter->name,
                'description' => $description,
                'url' => $url,
                'image' => $image,
                'logo' => filled($promoter->logo) ? Storage::disk('do')->url($promoter->logo) : null,
                'sameAs' => filled($promoter->website) ? $promoter->website : null,
            ]),
        );
    }

    /**
     * A festival's page: its banner or logo and what it says about itself,
     * as a promoter's, and a Festival record made up of its events - when
     * it runs, where, who puts it on, and each event within it.
     *
     * @param  Collection<int, Listing>  $listings  its events, with their venues
     */
    public static function festival(Festival $festival, Collection $listings): PageMeta
    {
        ['description' => $description, 'image' => $image] = self::profile($festival, "{$festival->name}: the festival's events, listed by Good on Paper.");
        $url = url()->current();
        $promoter = $festival->promoter;

        return new PageMeta(
            title: $festival->name,
            description: $description,
            image: $image ?? self::defaultImage(),
            url: $url,
            type: 'website',
            jsonLd: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Festival',
                'name' => $festival->name,
                'description' => $description,
                'url' => $url,
                'image' => $image,
                'startDate' => $listings->min(fn (Listing $listing): string => $listing->start_date->toDateString()),
                'endDate' => $listings->max(fn (Listing $listing): string => ($listing->end_date ?? $listing->start_date)->toDateString()),
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'location' => $listings->pluck('venue')->filter(fn (?Venue $venue): bool => (bool) $venue?->id)->unique('id')
                    ->map(fn (Venue $venue): array => ['@type' => 'Place', 'name' => $venue->name, 'address' => $venue->address ?? $venue->name])
                    ->values()->all() ?: null,
                'organizer' => $promoter ? [
                    '@type' => 'Organization',
                    'name' => $promoter->name,
                    'url' => route('Promoter', ['id' => $promoter->id, 'slug' => Str::slug($promoter->name)]),
                ] : null,
                'sameAs' => filled($festival->website) ? $festival->website : null,
                'subEvent' => $listings->map(fn (Listing $listing): array => [
                    '@type' => 'Event',
                    'name' => $listing->title,
                    'url' => route('Listing', ['id' => $listing->id, 'slug' => $listing->slug]),
                    'startDate' => $listing->start_date->toDateString(),
                ])->values()->all() ?: null,
            ]),
        );
    }

    /**
     * The shop's front page, in the words the office has set in Shop settings.
     */
    public static function shop(): PageMeta
    {
        return self::for(
            'The Good on Paper shop',
            Str::squish((string) ShopSettings::current()->meta_description),
        );
    }

    /**
     * A shelf in the shop: its own description and picture, or else a line
     * saying whose it is and the site's picture.
     */
    public static function shopCategory(ShopCategory $category): PageMeta
    {
        $description = (string) Str::of((string) $category->description)->squish();

        return self::for(
            $category->name,
            $description !== '' ? Str::limit($description, 200) : "{$category->name} from the Good on Paper shop.",
            filled($category->image) ? Storage::disk('do')->url($category->image) : null,
        );
    }

    /**
     * The latest issue's cover, the picture for any page without its own.
     * Looked up once a request.
     */
    public static function defaultImage(): ?string
    {
        if (! self::$defaultImageLooked) {
            $hero = Issue::query()->latest()->value('hero_image');

            self::$defaultImage = filled($hero) ? Storage::disk('do')->url($hero) : null;
            self::$defaultImageLooked = true;
        }

        return self::$defaultImage;
    }

    /**
     * Forget the cover looked up, for the next request in a long-running
     * process, and between tests.
     */
    public static function flush(): void
    {
        self::$defaultImage = null;
        self::$defaultImageLooked = false;
    }

    /**
     * How a promoter or a festival describes itself, and the picture it is
     * best known by: what it says about itself, cut to a sentence or two,
     * or the given line where it says nothing; its banner, or failing that
     * its logo.
     *
     * @return array{description: string, image: string|null}
     */
    private static function profile(Promoter|Festival $owner, string $otherwise): array
    {
        $about = (string) Str::of((string) $owner->about)->squish();
        $picture = collect([$owner->banner, $owner->logo])->first(fn (?string $path): bool => filled($path));

        return [
            'description' => $about !== '' ? Str::limit($about, 200) : $otherwise,
            'image' => $picture !== null ? Storage::disk('do')->url($picture) : null,
        ];
    }

    /**
     * @return array{'@type': string, name: string, url: string}
     */
    private static function website(): array
    {
        return ['@type' => 'WebSite', 'name' => FeatureMeta::PUBLISHER, 'url' => url('/')];
    }
}
