<?php

namespace App\Support;

use App\Models\Issue;
use App\Models\Venue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What the site's own pages - the home page, the listings, the magazine's
 * indexes, a venue - tell Facebook, X, WhatsApp and search engines about
 * themselves, where they have no record of their own to speak for them.
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
     * @return array{'@type': string, name: string, url: string}
     */
    private static function website(): array
    {
        return ['@type' => 'WebSite', 'name' => FeatureMeta::PUBLISHER, 'url' => url('/')];
    }
}
