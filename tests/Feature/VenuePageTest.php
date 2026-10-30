<?php

use App\Models\Venue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

test('a venue\'s page is given its cover photo, description, address and place', function () {
    $venue = Venue::factory()->create([
        'name' => 'The Yard Gallery',
        'address' => '1 Threadneedle Street',
        'town' => 'Stroud',
        'postcode' => 'GL5 1AA',
        'cover_photo' => 'venues/yard.webp',
        'description' => '<p>A gallery in a yard.</p>',
    ]);
    $venue->forceFill(['latitude' => 51.7457, 'longitude' => -2.2178])->save();

    $this->get(route('Venue', [$venue->id, 'the-yard-gallery']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Listings')
            ->where('venue.name', 'The Yard Gallery')
            ->where('venue.cover_photo', 'venues/yard.webp')
            ->where('venue.description', '<p>A gallery in a yard.</p>')
            ->where('venue.address', '1 Threadneedle Street')
            ->where('venue.latitude', 51.7457)
            ->where('venue.longitude', -2.2178)
        );
});

test('a venue that does not exist is not found', function () {
    $this->get(route('Venue', [99999, 'nowhere']))->assertNotFound();
});

test('a venue\'s website link is tagged as Good on Paper\'s referral, keeping its own query', function () {
    $venue = Venue::factory()->create(['url' => 'https://yard.example.com/visit?from=map&utm_medium=old']);

    $this->get(route('Venue', [$venue->id, 'the-yard']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where(
            'venue.website_link',
            "https://yard.example.com/visit?from=map&utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=venue-{$venue->id}"
        ));
});

test('a venue with no website has no website link', function () {
    $venue = Venue::factory()->create(['url' => null]);

    $this->get(route('Venue', [$venue->id, 'nowhere']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('venue.website_link', null));
});

test('a venue\'s page is given its phone number and email', function () {
    $venue = Venue::factory()->create([
        'phone' => '01453 123456',
        'email' => 'hello@yardgallery.test',
    ]);

    $this->get(route('Venue', [$venue->id, 'yard']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('venue.phone', '01453 123456')
            ->where('venue.email', 'hello@yardgallery.test')
        );
});
