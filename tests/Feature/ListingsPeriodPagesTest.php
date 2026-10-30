<?php

use App\Models\Listing;

beforeEach(function () {
    $this->withoutVite();
});

test('the today page shows only what is on today, under the Today tab', function () {
    $onToday = Listing::factory()->create(['start_date' => today()->toDateString(), 'end_date' => null]);
    Listing::factory()->create(['start_date' => today()->addWeek()->toDateString(), 'end_date' => null]);

    $this->get('/listings/today')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Listings')
            ->where('title', "Today's listings.")
            ->where('today', 1)
            ->where('all', false)
            ->has('listings', 1)
            ->where('listings.0.id', $onToday->id)
        );
});

test('the listings home page shows everything coming up, under the All tab', function () {
    Listing::factory()->create(['start_date' => today()->toDateString(), 'end_date' => null]);
    Listing::factory()->create(['start_date' => today()->addWeek()->toDateString(), 'end_date' => null]);

    $this->get('/listings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Listings')
            ->where('all', true)
            ->where('today', false)
            ->has('listings', 2)
        );
});

test('the old address for all the listings sends people to the listings home page for good', function () {
    $this->get('/listings/all')
        ->assertMovedPermanently()
        ->assertRedirect('/listings');
});
