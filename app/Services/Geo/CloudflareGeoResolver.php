<?php

namespace App\Services\Geo;

use Illuminate\Http\Request;

/**
 * Resolves visitor location from Cloudflare's visitor location headers.
 *
 * Requires the "Add visitor location headers" Managed Transform to be
 * enabled on the zone. Header values containing non-ASCII characters
 * arrive URL-encoded, hence the decode step.
 *
 * Only trustworthy when the origin is reachable exclusively via
 * Cloudflare — lock the origin to Cloudflare's IP ranges.
 */
final class CloudflareGeoResolver implements GeoResolver
{
    /**
     * Cloudflare country codes that mean "unknown" (XX) or "Tor" (T1),
     * neither of which is a real location.
     *
     * @var array<int, string>
     */
    private const NON_LOCATIONS = ['XX', 'T1'];

    /**
     * Resolve the visitor location from cf-* request headers.
     */
    public function resolve(Request $request): GeoResult
    {
        $country = strtoupper((string) $request->header('cf-ipcountry', ''));

        if ($country === '' || in_array($country, self::NON_LOCATIONS, true)) {
            $country = null;
        }

        return new GeoResult(
            city: $this->decode($request->header('cf-ipcity')),
            region: $this->decode($request->header('cf-region')),
            countryCode: $country,
        );
    }

    /**
     * URL-decode a header value, normalising blanks to null.
     */
    private function decode(?string $value): ?string
    {
        $value = $value !== null ? trim(urldecode($value)) : null;

        return ($value === null || $value === '') ? null : $value;
    }
}
