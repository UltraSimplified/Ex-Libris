<?php

use App\Models\Venue;
use App\Services\Geocoding\PostcodeGeocoder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // The suite keeps venues off postcodes.io; these tests are about the
    // lookup itself, so the real geocoder goes back against a faked service.
    app()->instance(PostcodeGeocoder::class, new PostcodeGeocoder);
});

/**
 * postcodes.io's answer for a postcode at the given place.
 */
function postcodeAt(float $latitude, float $longitude): array
{
    return ['status' => 200, 'result' => ['latitude' => $latitude, 'longitude' => $longitude]];
}

test('a venue is placed on the map from its postcode when it is saved', function () {
    Http::fake(['api.postcodes.io/postcodes/GL51AA' => Http::response(postcodeAt(51.7457, -2.2178))]);

    $venue = Venue::factory()->create(['postcode' => 'gl5 1aa']);

    expect($venue->fresh()->latitude)->toBe(51.7457)
        ->and($venue->fresh()->longitude)->toBe(-2.2178);

    Http::assertSent(fn (Request $request): bool => $request->url() === PostcodeGeocoder::ENDPOINT.'GL51AA');
});

test('moving to another postcode moves the pin', function () {
    Http::fake([
        'api.postcodes.io/postcodes/GL51AA' => Http::response(postcodeAt(51.7457, -2.2178)),
        'api.postcodes.io/postcodes/GL61QA' => Http::response(postcodeAt(51.7183, -2.2795)),
    ]);

    $venue = Venue::factory()->create(['postcode' => 'GL5 1AA']);
    $venue->update(['postcode' => 'GL6 1QA']);

    expect($venue->fresh()->latitude)->toBe(51.7183)
        ->and($venue->fresh()->longitude)->toBe(-2.2795);
});

test('a pin set by hand stays where it was put', function () {
    Http::fake(['api.postcodes.io/*' => Http::response(postcodeAt(51.7457, -2.2178))]);

    $venue = Venue::factory()->create(['postcode' => 'GL5 1AA']);
    $venue->forceFill(['latitude' => 51.7461234, 'longitude' => -2.2161234])->save();
    $venue->update(['name' => 'Renamed']);

    expect($venue->fresh()->latitude)->toBe(51.7461234)
        ->and($venue->fresh()->longitude)->toBe(-2.2161234);

    // Only the first save looked the postcode up.
    Http::assertSentCount(1);
});

test('a placed venue whose postcode has not changed is not looked up again', function () {
    Http::fake(['api.postcodes.io/*' => Http::response(postcodeAt(51.7457, -2.2178))]);

    $venue = Venue::factory()->create(['postcode' => 'GL5 1AA']);
    $venue->update(['url' => 'https://example.com']);

    Http::assertSentCount(1);
});

test('a postcode the service does not know leaves the venue off the map', function () {
    Http::fake(['api.postcodes.io/*' => Http::response(['status' => 404, 'error' => 'Invalid postcode'], 404)]);

    $venue = Venue::factory()->create(['postcode' => 'ZZ9 9ZZ']);

    expect($venue->fresh()->latitude)->toBeNull()
        ->and($venue->fresh()->longitude)->toBeNull();
});

test('the venue is still saved when the service cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Timed out'));

    $venue = Venue::factory()->create(['postcode' => 'GL5 1AA', 'name' => 'Still saved']);

    expect($venue->fresh()->name)->toBe('Still saved')
        ->and($venue->fresh()->latitude)->toBeNull();
});

test('a venue with no postcode is not looked up at all', function () {
    Http::fake();

    Venue::factory()->create(['postcode' => null]);

    Http::assertNothingSent();
});

test('venues saved before placing began are put on the map by the command', function () {
    Http::fake(['api.postcodes.io/*' => Http::response(postcodeAt(51.7457, -2.2178))]);

    // Saved quietly, as the venues already in the database were.
    $unplaced = Venue::withoutEvents(fn () => Venue::factory()->create(['postcode' => 'GL5 1AA']));
    $noPostcode = Venue::withoutEvents(fn () => Venue::factory()->create(['postcode' => null]));

    $this->artisan('venues:locate')->assertSuccessful();

    expect($unplaced->fresh()->latitude)->toBe(51.7457)
        ->and($noPostcode->fresh()->latitude)->toBeNull();
});
