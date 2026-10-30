<?php

namespace App\Services\Shop;

use App\Models\Address;

/**
 * Addresses between Stripe's shape (line1, city, state, postal_code, and a
 * two-letter country) and ours (line_one, town, county, postcode).
 */
class StripeAddress
{
    /**
     * An account's address as Stripe wants it. The shop only posts within
     * the UK, so the country is always GB whatever the account wrote.
     *
     * @return array<string, string>
     */
    public static function fromAccount(Address $address): array
    {
        return array_filter([
            'line1' => (string) $address->line_one,
            'line2' => (string) $address->line_two,
            'city' => (string) $address->town,
            'state' => (string) $address->county,
            'postal_code' => (string) $address->postcode,
            'country' => 'GB',
        ], 'filled');
    }

    /**
     * An address Stripe collected, as an order keeps it.
     *
     * @param  array<string, ?string>|null  $address
     * @return array<string, ?string>|null
     */
    public static function toOrder(?array $address, ?string $name = null): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'name' => $name,
            'line_one' => $address['line1'] ?? null,
            'line_two' => $address['line2'] ?? null,
            'town' => $address['city'] ?? null,
            'county' => $address['state'] ?? null,
            'postcode' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? null,
        ];
    }
}
