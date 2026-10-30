<?php

namespace App\Nova\Metrics;

use App\Models\AdvertEvent;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

/**
 * Base partition of advert events by visitor locality bucket.
 *
 * Scoped to the advert being viewed and to a single event type, and
 * floored at the date location tracking went live on this site, so the
 * rows recorded before Cloudflare was in front of it don't permanently
 * dominate the "Unknown" slice.
 */
abstract class EventsByLocality extends Partition
{
    /**
     * The moment the Cloudflare geolocation pipeline went live on
     * new.goodonpaper.info: the site was launched on 23 October 2026
     * without Cloudflare in front of it, and switched to it on the 25th.
     * Events before this are structurally unresolvable (no IP retained).
     */
    protected const GEO_LIVE_FROM = '2026-10-25 21:57:36';

    /**
     * The advert event type this metric counts.
     */
    abstract protected function eventType(): string;

    /**
     * Calculate event counts grouped by locality bucket.
     */
    public function calculate(NovaRequest $request): PartitionResult
    {
        return $this->count(
            $request,
            AdvertEvent::query()
                ->where('advert_id', $request->resourceId)
                ->where('type', $this->eventType())
                ->where('created_at', '>=', self::GEO_LIVE_FROM),
            'locality_bucket'
        )->label(fn (?string $value) => $value ?? 'Unknown');
    }
}
