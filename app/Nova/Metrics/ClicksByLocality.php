<?php

namespace App\Nova\Metrics;

/**
 * Where advert impressions are being seen — the advertiser-facing
 * reach metric.
 */
class ClicksByLocality extends EventsByLocality
{
    /**
     * Count click events only.
     */
    protected function eventType(): string
    {
        return 'click';
    }

    /**
     * The displayable name of the metric.
     */
    public function name(): string
    {
        return 'Clicks by Location';
    }

    /**
     * Unique URI key (also Nova's cache key for this metric).
     */
    public function uriKey(): string
    {
        return 'clicks-by-locality';
    }
}
