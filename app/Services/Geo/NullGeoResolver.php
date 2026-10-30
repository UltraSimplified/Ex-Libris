<?php

namespace App\Services\Geo;

use Illuminate\Http\Request;

/**
 * No-op resolver for environments without Cloudflare in front
 * (local dev, testing). Always returns an empty result so the
 * ingestion pipeline behaves identically everywhere.
 */
final class NullGeoResolver implements GeoResolver
{
    /**
     * Return an empty location result regardless of the request.
     */
    public function resolve(Request $request): GeoResult
    {
        return GeoResult::empty();
    }
}
