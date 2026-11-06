<?php

namespace App\Support;

use App\Models\Feature;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

/**
 * The Listing blocks in a feature's body, each given the listing it was
 * pointed at in the Office, so the page can show it as a row from the
 * listings wherever the block was dropped.
 *
 * Held to the listings still to come or under way, as the listing at the
 * foot of a feature is: once an event has finished its block reads as
 * empty, though the choice stays in the Office.
 */
class FeatureBodyListings
{
    /**
     * The name the Listing block is stored under in the body.
     */
    public const LAYOUT = 'listing';

    /**
     * @param  array<int, object>|null  $layouts
     * @return array<int, object>|null
     */
    public static function attach(?array $layouts): ?array
    {
        $blocks = array_filter(
            $layouts ?? [],
            fn (object $layout): bool => ($layout->layout ?? null) === self::LAYOUT,
        );

        if ($blocks === []) {
            return $layouts;
        }

        $listings = Listing::current()
            ->with(['venue', 'category'])
            ->findMany(array_map(self::chosenId(...), $blocks))
            ->keyBy('id');

        foreach ($blocks as $block) {
            /*
             * A block saved with nothing in it is stored with its
             * attributes as an empty list rather than an object.
             */
            if (! is_object($block->attributes ?? null)) {
                $block->attributes = new stdClass;
            }

            $block->attributes->listing = $listings->get(self::chosenId($block));
        }

        return $layouts;
    }

    /**
     * The listings a block can be pointed at in the Office, by id: those
     * still to come or under way, and whichever this feature's blocks
     * already point at, so a finished one still reads as chosen rather than
     * blank. Each carries its venue and first date, as the listing at the
     * foot of a feature does, since the same event is often listed more
     * than once.
     *
     * @return array<int, string>
     */
    public static function options(?Feature $feature): array
    {
        $body = is_string($feature?->body) ? json_decode($feature->body, true) : null;

        $chosen = collect(is_array($body) ? $body : [])
            ->where('layout', self::LAYOUT)
            ->pluck('attributes.listing_id')
            ->filter()
            ->all();

        return Listing::query()
            ->with('venue')
            ->where(fn (Builder $query) => $query->current()->orWhereIn('id', $chosen))
            ->orderBy('start_date')
            ->orderBy('title')
            ->get()
            ->mapWithKeys(fn (Listing $listing): array => [
                $listing->id => collect([$listing->title, $listing->venue?->name, $listing->start_date->format('j M Y')])
                    ->filter()
                    ->implode(' · '),
            ])
            ->all();
    }

    /**
     * The listing a block was pointed at, or 0 for none.
     */
    private static function chosenId(object $block): int
    {
        return (int) ($block->attributes->listing_id ?? 0);
    }
}
