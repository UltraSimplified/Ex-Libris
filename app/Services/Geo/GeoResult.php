<?php

namespace App\Services\Geo;

/**
 * Immutable value object describing a resolved visitor location.
 *
 * All fields are nullable: absence of data (local dev, non-proxied
 * requests, unresolvable IPs) is a normal state, not an error.
 */
final readonly class GeoResult
{
    /**
     * @param  string|null  $city  Human-readable city name, e.g. "Stroud".
     * @param  string|null  $region  Region name, e.g. "England".
     * @param  string|null  $countryCode  ISO 3166-1 alpha-2 code, e.g. "GB".
     */
    public function __construct(
        public ?string $city = null,
        public ?string $region = null,
        public ?string $countryCode = null,
    ) {}

    /**
     * Create an empty result for requests with no location data.
     */
    public static function empty(): self
    {
        return new self;
    }

    /**
     * Determine whether any location data was resolved at all.
     */
    public function isEmpty(): bool
    {
        return $this->city === null
               && $this->region === null
               && $this->countryCode === null;
    }
}
