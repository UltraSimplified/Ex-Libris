<?php

namespace App\Nova\Metrics;

/**
 * Where advert impressions are being seen — the advertiser-facing
 * reach metric.
 */
class ImpressionsByLocality extends EventsByLocality
{
    /**
     * Count impression events only.
     */
    protected function eventType(): string
    {
        return 'impression';
    }

    /**
     * The displayable name of the metric.
     */
    public function name(): string
    {
        return 'Impressions by Location';
    }

    /**
     * Unique URI key (also Nova's cache key for this metric).
     */
    public function uriKey(): string
    {
        return 'impressions-by-locality';
    }
}
