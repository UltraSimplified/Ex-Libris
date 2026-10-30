<?php

namespace App\Services\Geo;

/**
 * Maps a resolved location into an editorial "locality bucket" for
 * advertiser-facing stats (e.g. "Stroud district", "Bristol").
 *
 * Bucket definitions live in config/geo.php. Matching is
 * case-insensitive on the city name; unmatched GB cities fall
 * through to "Elsewhere UK", non-GB to "International".
 */
final class LocalityBucketer
{
    public const ELSEWHERE_UK = 'Elsewhere, UK';

    public const INTERNATIONAL = 'International';

    /**
     * Lowercased city => bucket lookup, built once from config.
     *
     * @var array<string, string>
     */
    private array $cityIndex = [];

    /**
     * @param  array<string, array<int, string>>  $buckets  Bucket name => list of city names.
     */
    public function __construct(array $buckets)
    {
        foreach ($buckets as $bucket => $cities) {
            foreach ($cities as $city) {
                $this->cityIndex[mb_strtolower($city)] = $bucket;
            }
        }
    }

    /**
     * Determine the locality bucket for a resolved location.
     *
     * Returns null when no location data exists at all, so "no data"
     * remains distinguishable from "definitely elsewhere" in the stats.
     */
    public function bucketFor(GeoResult $geo): ?string
    {
        if ($geo->isEmpty()) {
            return null;
        }

        if ($geo->city !== null) {
            $bucket = $this->cityIndex[mb_strtolower($geo->city)] ?? null;

            if ($bucket !== null) {
                return $bucket;
            }
        }

        return $geo->countryCode === 'GB'
            ? self::ELSEWHERE_UK
            : self::INTERNATIONAL;
    }
}
