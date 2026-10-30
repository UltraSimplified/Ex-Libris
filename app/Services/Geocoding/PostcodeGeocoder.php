<?php

namespace App\Services\Geocoding;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Where a UK postcode is, from postcodes.io.
 *
 * postcodes.io is free and needs no key: it serves the Office for National
 * Statistics' postcode directory, which places each postcode at the middle
 * of the dozen or so addresses it covers - close enough to put a venue on
 * a street map, and exact enough once the Office nudges the pin.
 *
 * It never throws: a postcode it does not know, or a service that is down,
 * leaves the venue without a place rather than stopping it being saved.
 */
class PostcodeGeocoder
{
    public const ENDPOINT = 'https://api.postcodes.io/postcodes/';

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function locate(?string $postcode): ?array
    {
        $postcode = strtoupper(preg_replace('/\s+/', '', (string) $postcode));

        if ($postcode === '' || ! preg_match('/^[A-Z0-9]{5,7}$/', $postcode)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->acceptJson()->get(self::ENDPOINT.$postcode);
        } catch (ConnectionException $exception) {
            Log::warning('Could not reach postcodes.io to place a postcode.', [
                'postcode' => $postcode,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $latitude = $response->json('result.latitude');
        $longitude = $response->json('result.longitude');

        if (! $response->successful() || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return ['latitude' => (float) $latitude, 'longitude' => (float) $longitude];
    }
}
