<?php

use App\Models\Feature;
use App\Models\Listing;
use App\Models\User;

test('a feature carries its listing, with the venue and category its row shows', function () {
    $listing = Listing::factory()->create(['title' => 'TEST ENTRY gig']);
    $feature = Feature::factory()->create(['listing_id' => $listing->id]);

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Magazine/Feature')
            ->where('feature.listing.id', $listing->id)
            ->where('feature.listing.venue.id', $listing->venue_id)
            ->where('feature.listing.category.id', $listing->category_id)
        );
});

test('a feature with no listing carries none', function () {
    $feature = Feature::factory()->create();

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('feature.listing', null));
});

test('a listing that has finished is left off the feature', function () {
    $listing = Listing::factory()->create([
        'start_date' => today()->subDays(3),
        'end_date' => today()->subDay(),
    ]);
    $feature = Feature::factory()->create(['listing_id' => $listing->id]);

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('feature.listing', null));
});

test('a listing on its last day is still shown', function () {
    $listing = Listing::factory()->create([
        'start_date' => today()->subDays(2),
        'end_date' => today(),
    ]);
    $feature = Feature::factory()->create(['listing_id' => $listing->id]);

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertInertia(fn ($page) => $page->where('feature.listing.id', $listing->id));
});

test('the office preview shows the listing too', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $listing = Listing::factory()->create();
    $feature = Feature::factory()->create(['listing_id' => $listing->id]);

    $this->actingAs($staff)
        ->get(route('features.preview', $feature))
        ->assertInertia(fn ($page) => $page->where('feature.listing.id', $listing->id));
});
