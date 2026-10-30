<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Venue;

/**
 * end_date is only set for events running more than one day, so most
 * listings have none. Every query that compared against end_date directly
 * dropped them, which is what made listings invisible on the public site.
 */
function listingRunning(?string $start, ?string $end = null): Listing
{
    return Listing::factory()->create([
        'start_date' => $start,
        'end_date' => $end,
    ]);
}

test('a single day listing with no end date is still current', function () {
    $today = listingRunning(today()->toDateString());
    $future = listingRunning(today()->addWeek()->toDateString());
    $past = listingRunning(today()->subDay()->toDateString());

    $current = Listing::current()->pluck('id');

    expect($current)->toContain($today->id, $future->id)
        ->and($current)->not->toContain($past->id);
});

test('a run that is under way is current until its last day passes', function () {
    $running = listingRunning(today()->subMonth()->toDateString(), today()->addMonth()->toDateString());
    $endsToday = listingRunning(today()->subMonth()->toDateString(), today()->toDateString());
    $endedYesterday = listingRunning(today()->subMonth()->toDateString(), today()->subDay()->toDateString());

    $current = Listing::current()->pluck('id');

    expect($current)->toContain($running->id, $endsToday->id)
        ->and($current)->not->toContain($endedYesterday->id);
});

test('a listing starting today is not hidden by the time of day', function () {
    // start_date is stored at midnight, so comparing it against now() at any
    // point after 00:00 used to exclude everything starting today.
    $startsToday = listingRunning(today()->toDateString());

    expect(Listing::current()->pluck('id'))->toContain($startsToday->id);
});

test('running on a day catches single day and spanning listings alike', function () {
    $onTheDay = listingRunning(today()->toDateString());
    $spanning = listingRunning(today()->subDays(3)->toDateString(), today()->addDays(3)->toDateString());
    $tomorrowOnly = listingRunning(today()->addDay()->toDateString());

    $ids = Listing::runningOn(today())->pluck('id');

    expect($ids)->toContain($onTheDay->id, $spanning->id)
        ->and($ids)->not->toContain($tomorrowOnly->id);
});

test('a range catches a run that overlaps it at either edge', function () {
    $from = today()->addWeek();
    $to = today()->addWeek()->addDays(6);

    $startsBeforeEndsInside = listingRunning(today()->toDateString(), $from->copy()->addDay()->toDateString());
    $startsInsideEndsAfter = listingRunning($to->copy()->subDay()->toDateString(), today()->addMonths(2)->toDateString());
    $entirelyInside = listingRunning($from->copy()->addDays(2)->toDateString());
    $entirelyBefore = listingRunning(today()->toDateString());
    $entirelyAfter = listingRunning(today()->addMonths(3)->toDateString());

    $ids = Listing::runningBetween($from, $to)->pluck('id');

    expect($ids)->toContain($startsBeforeEndsInside->id, $startsInsideEndsAfter->id, $entirelyInside->id)
        ->and($ids)->not->toContain($entirelyBefore->id, $entirelyAfter->id);
});

test('the listings pages show current listings that have no end date', function () {
    $category = Category::factory()->create(['slug' => 'music', 'name' => 'Music']);
    $venue = Venue::factory()->create();

    $listing = Listing::factory()->create([
        'title' => 'Tonight Only',
        'category_id' => $category->id,
        'venue_id' => $venue->id,
        'start_date' => today()->toDateString(),
        'end_date' => null,
    ]);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('listings.0.id', $listing->id));

    $this->get(route('Category', ['slug' => 'music']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('listings.0.id', $listing->id));

    $this->get(route('Venue', ['id' => $venue->id, 'slug' => 'x']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('listings.0.id', $listing->id));

    $this->get(route('today'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('listings.0.id', $listing->id));
});

test('a finished listing is not shown on the listings page', function () {
    Listing::factory()->create([
        'title' => 'Long Gone',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', 0));
});

test('a date range page includes a run that spans it', function () {
    $spanning = Listing::factory()->create([
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonth()->toDateString(),
    ]);

    $this->get('/listings/dates/'.today()->toDateString().'/'.today()->addDay()->toDateString())
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('listings.0.id', $spanning->id));
});
