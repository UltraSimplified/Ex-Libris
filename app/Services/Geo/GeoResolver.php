<?php

namespace App\Services\Geo;

use Illuminate\Http\Request;

/**
 * Contract for resolving a visitor's approximate location from a request.
 *
 * Implementations must never throw for missing/unparseable data —
 * telemetry ingestion should degrade to nulls, not fail.
 */
interface GeoResolver
{
    /**
     * Resolve the visitor location for the given request.
     */
    public function resolve(Request $request): GeoResult;
}
