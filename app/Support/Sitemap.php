<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Feature;
use App\Models\Listing;
use App\Models\Page;
use App\Models\Product;
use App\Models\Promoter;
use App\Models\ShopCategory;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Every page the public can reach that a search engine should know about,
 * for sitemap.xml.
 *
 * Only what is published and still to come: drafts, private features and
 * events that have been and gone are left out, as are pages that are only a
 * view of others (a choice of dates, a filter, someone's bookmarks). A page
 * is dated by the last change to what is on it, which is the one thing in a
 * sitemap Google pays attention to; where that cannot be said honestly, it
 * goes undated.
 */
class Sitemap
{
    /**
     * @return Collection<int, array{loc: string, lastmod: ?string}>
     */
    public static function entries(): Collection
    {
        return collect([
            ...self::listings(),
            ...self::magazine(),
            ...self::places(),
            ...self::writers(),
            ...self::pages(),
            ...self::shop(),
        ]);
    }

    /**
     * The listings, the genre pages over them and each event still to come.
     *
     * @return list<array{loc: string, lastmod: ?string}>
     */
    private static function listings(): array
    {
        $listings = Listing::current()->get(['id', 'slug', 'title', 'updated_at']);

        $genres = Category::query()
            ->whereHas('listings', fn ($upcoming) => $upcoming->current())
            ->withMax(['listings' => fn ($upcoming) => $upcoming->current()], 'updated_at')
            ->orderBy('name')
            ->get(['id', 'slug']);

        return [
            self::entry(route('Listings'), $listings->max('updated_at')),
            self::entry(route('today')),
            self::entry(route('ThisWeek')),
            ...$genres->map(fn (Category $genre): array => self::entry(
                route('Category', $genre->slug),
                $genre->listings_max_updated_at,
            )),
            ...$listings->map(fn (Listing $listing): array => self::entry(
                route('Listing', ['id' => $listing->id, 'slug' => $listing->slug ?: Str::slug($listing->title)]),
                $listing->updated_at,
            )),
        ];
    }

    /**
     * The magazine: its published features, the tags they are filed under,
     * the archive of print issues and where to pick one up.
     *
     * @return list<array{loc: string, lastmod: ?string}>
     */
    private static function magazine(): array
    {
        $features = Feature::query()
            ->where('status', FeatureStatus::Published)
            ->with('tag')
            ->get(['id', 'slug', 'tag_id', 'updated_at']);

        $tags = $features->pluck('tag')->filter()->unique('id');

        return [
            self::entry(route('home'), $features->max('updated_at')),
            self::entry(route('Features'), $features->max('updated_at')),
            ...$tags->map(fn ($tag): array => self::entry(route('features.tag', $tag->slug))),
            ...$features->map(fn (Feature $feature): array => self::entry(
                route('feature', ['id' => $feature->id, 'slug' => $feature->slug]),
                $feature->updated_at,
            )),
            self::entry(route('Archives')),
            self::entry(url('magazine/stockists')),
        ];
    }

    /**
     * Each venue's and each promoter's own page, under its name.
     *
     * @return list<array{loc: string, lastmod: ?string}>
     */
    private static function places(): array
    {
        return [
            ...Venue::query()->get(['id', 'name', 'updated_at'])->map(fn (Venue $venue): array => self::entry(
                route('Venue', ['id' => $venue->id, 'slug' => Str::slug($venue->name)]),
                $venue->updated_at,
            )),
            ...Promoter::query()->get(['id', 'name', 'updated_at'])->map(fn (Promoter $promoter): array => self::entry(
                route('Promoter', ['id' => $promoter->id, 'slug' => Str::slug($promoter->name)]),
                $promoter->updated_at,
            )),
        ];
    }

    /**
     * A page for each writer. Undated: an account changes for reasons that
     * have nothing to do with the page.
     *
     * @return list<array{loc: string, lastmod: ?string}>
     */
    private static function writers(): array
    {
        return User::writers()
            ->get(['id', 'name'])
            ->map(fn (User $writer): array => self::entry(route('writer', Str::slug($writer->name))))
            ->all();
    }

    /**
     * The pages edited in the Office, each under its own slug.
     *
     * @return list<array{loc: string, lastmod: ?string}>
     */
    private static function pages(): array
    {
        return Page::query()
            ->get(['id', 'slug', 'updated_at'])
            ->map(fn (Page $page): array => self::entry(route('page', $page->slug), $page->updated_at))
            ->all();
    }

    /**
     * The shop, its published categories and what is on sale in them.
     *
     * @return list<array{loc: string, lastmod: ?string}>
     */
    private static function shop(): array
    {
        $products = Product::onSale()->with('shopCategory:id,slug')->get(['id', 'slug', 'shop_category_id', 'updated_at']);

        return [
            self::entry(route('Shop')),
            ...ShopCategory::query()
                ->where('is_published', true)
                ->get(['id', 'slug', 'updated_at'])
                ->map(fn (ShopCategory $category): array => self::entry(
                    route('shop.category', $category->slug),
                    $category->updated_at,
                )),
            ...$products->map(fn (Product $product): array => self::entry(
                route('shop.product', [$product->shopCategory->slug, $product->slug]),
                $product->updated_at,
            )),
        ];
    }

    /**
     * @return array{loc: string, lastmod: ?string}
     */
    private static function entry(string $loc, CarbonInterface|string|null $lastModified = null): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastModified === null ? null : Carbon::parse($lastModified)->toAtomString(),
        ];
    }
}
