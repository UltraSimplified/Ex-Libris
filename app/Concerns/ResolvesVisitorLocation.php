<?php

namespace App\Concerns;

use App\Services\Geo\GeoResolver;
use App\Services\Geo\LocalityBucketer;
use Illuminate\Http\Request;

/**
 * Shared helper for controllers that record advert telemetry events.
 *
 * Resolves the visitor's approximate location and locality bucket
 * into a column => value array ready to merge into an AdvertEvent
 * create() payload. Always safe: unresolvable locations yield nulls.
 */
trait ResolvesVisitorLocation
{
    /**
     * Resolve and bucket the visitor location for an advert event payload.
     *
     * @return array{country_code: ?string, region: ?string, city: ?string, locality_bucket: ?string}
     */
    protected function locationAttributes(Request $request): array
    {
        $geo = app(GeoResolver::class)->resolve($request);

        return [
            'country_code' => $geo->countryCode,
            'region' => $geo->region,
            'city' => $geo->city,
            'locality_bucket' => app(LocalityBucketer::class)->bucketFor($geo),
        ];
    }
}
