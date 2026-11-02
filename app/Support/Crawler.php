<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tells a crawler or a script from a person, by the name its request gives.
 * The lookbehind spares the phones made by Cubot, whose name ends in "bot".
 */
class Crawler
{
    private const SIGNATURES = '/(?<!cu)bot\b|crawler|spider|slurp|facebookexternalhit|mediapartners-google|google-inspectiontool|curl\/|wget\/|python-requests/i';

    public static function sent(Request $request): bool
    {
        $userAgent = (string) $request->userAgent();

        return $userAgent === '' || preg_match(self::SIGNATURES, $userAgent) === 1;
    }
}
