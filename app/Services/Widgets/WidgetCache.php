<?php

namespace App\Services\Widgets;

use App\Models\Listing;
use App\Models\Promoter;
use App\Models\Venue;
use App\Models\Widget;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Keeps rendered widgets, so a busy venue's home page does not cost a query
 * per view, and lets them go the moment anything they show changes.
 *
 * Each venue and promoter has a version, stored in the cache, which is part
 * of the key every one of its widgets is kept under. Saving a listing, the
 * venue or the promoter changes the version, and every widget showing them
 * is rendered afresh on its next view. A widget's own changes move its
 * updated_at, which is in the key too.
 */
class WidgetCache
{
    /**
     * Long enough to spare the database on a busy page, short enough that a
     * date passing takes a finished event off within the hour.
     */
    private const SECONDS = 3600;

    /**
     * The rendered widget, from the cache or made now and kept.
     *
     * @param  Closure(): string  $render
     */
    public function remember(Widget $widget, string $variant, Closure $render): string
    {
        $key = implode(':', [
            'widget',
            $widget->id,
            $widget->updated_at?->getTimestamp(),
            $this->version($widget->owner_type, $widget->owner_id),
            today()->toDateString(),
            $variant,
        ]);

        return Cache::remember($key, self::SECONDS, $render);
    }

    /**
     * Let go of every widget showing this listing, at the venue and for the
     * promoter it has now and at any it has just been moved from.
     */
    public function forgetListing(Listing $listing): void
    {
        foreach (['venue_id' => Venue::class, 'promoter_id' => Promoter::class] as $column => $type) {
            collect([$listing->{$column}, $listing->getOriginal($column)])
                ->filter()
                ->unique()
                ->each(fn (int|string $id) => $this->forgetOwner($type, (int) $id));
        }
    }

    /**
     * Let go of every widget belonging to a venue or promoter.
     */
    public function forgetOwner(string $type, int $id): void
    {
        Cache::forever($this->versionKey($type, $id), Str::random(8));
    }

    private function version(string $type, int $id): string
    {
        return (string) Cache::get($this->versionKey($type, $id), '0');
    }

    private function versionKey(string $type, int $id): string
    {
        return 'widgets:owner:'.class_basename($type).':'.$id;
    }
}
