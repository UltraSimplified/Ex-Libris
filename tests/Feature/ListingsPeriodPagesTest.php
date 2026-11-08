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

test('the today page leaves out a listing whose specific days do not include today', function () {
    $this->travelTo('2026-06-10 12:00');
    Listing::factory()->onDays('2026-06-06', '2026-06-07', '2026-06-13', '2026-06-14', '2026-06-20')->create();
    $everyDay = Listing::factory()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-30']);

    $this->get('/listings/today')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('listings', 1)
            ->where('listings.0.id', $everyDay->id)
        );
});

test('the today page shows a listing on one of its specific days', function () {
    $this->travelTo('2026-06-13 12:00');
    $festival = Listing::factory()->onDays('2026-06-06', '2026-06-07', '2026-06-13', '2026-06-14', '2026-06-20')->create();

    $this->get('/listings/today')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('listings', 1)
            ->where('listings.0.id', $festival->id)
        );
});

test('the this week page counts specific days from today to Sunday', function (string $now, int $shown) {
    $this->travelTo($now);
    Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();

    $this->get('/listings/thisweek')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', $shown));
})->with([
    'Monday, the day before a quiz' => ['2026-11-16 12:00', 1],
    'Wednesday, the day after it' => ['2026-11-18 12:00', 0],
]);

test('a date range page leaves out a listing with none of its specific days in the range', function () {
    $this->travelTo('2026-11-10 12:00');
    Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();

    $this->get('/listings/dates/2026-11-18/2026-11-30')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', 0));
});

test('the listings home page shows a listing on specific days until its last one has passed', function (string $now, int $shown) {
    $this->travelTo($now);
    Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();

    $this->get('/listings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', $shown));
})->with([
    'between two of its days' => ['2026-11-10 12:00', 1],
    'the day after its last' => ['2026-12-16 12:00', 0],
]);

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
