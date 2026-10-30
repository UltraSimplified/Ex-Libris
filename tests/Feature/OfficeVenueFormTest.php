<?php

use App\Models\User;
use App\Models\Venue;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
});

/**
 * The attributes a Nova view of a venue offers.
 *
 * @return list<string>
 */
function venueFieldAttributes(string $uri, string $key = 'fields'): array
{
    return collect(test()->getJson($uri)->assertOk()->json($key))
        ->pluck('attribute')
        ->all();
}

test('the venue form in the office leaves the place to the postcode', function () {
    $venue = Venue::factory()->create();

    expect(venueFieldAttributes('/nova-api/venues/creation-fields'))
        ->toContain('postcode', 'phone', 'email')
        ->not->toContain('latitude', 'longitude');

    expect(venueFieldAttributes("/nova-api/venues/{$venue->id}/update-fields"))
        ->toContain('postcode', 'phone', 'email')
        ->not->toContain('latitude', 'longitude');
});

test('the venue page in the office still shows where it was placed', function () {
    $venue = Venue::factory()->create();

    expect(venueFieldAttributes("/nova-api/venues/{$venue->id}", 'resource.fields'))
        ->toContain('latitude', 'longitude');
});
