<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\Venue;

/**
 * Every listing view carries a keyword filter that hides the rows that do
 * not match. The filtering itself is done in the page - everything is
 * already there - so what matters on this side is that everything it
 * searches by actually arrives.
 */
test('the full listing carries the promoter each event is under', function () {
    $promoter = Promoter::factory()->create(['name' => 'Just SUST']);

    Listing::factory()->create([
        'promoter_id' => $promoter->id,
        'start_date' => today()->addWeek(),
    ]);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Listings')
            ->where('listings.0.promoter.name', 'Just SUST')
        );
});

test('an event under no promoter still reaches the page', function () {
    Listing::factory()->create([
        'promoter_id' => null,
        'start_date' => today()->addWeek(),
    ]);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('listings.0.promoter', null));
});

/**
 * Venue, genre, title and price are what the filter reads besides the
 * promoter. They were already being sent; this is here so that stays true.
 */
test('the full listing carries everything the filter searches by', function () {
    $venue = Venue::factory()->create(['name' => 'The Sub Rooms']);
    $category = Category::factory()->create(['name' => 'Music']);
    $promoter = Promoter::factory()->create(['name' => 'Emergence Restock']);

    Listing::factory()->create([
        'title' => 'Late Night Jazz',
        'venue_id' => $venue->id,
        'category_id' => $category->id,
        'promoter_id' => $promoter->id,
        'ticket_price' => 'Free',
        'start_date' => today()->addWeek(),
    ]);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listings.0.title', 'Late Night Jazz')
            ->where('listings.0.venue.name', 'The Sub Rooms')
            ->where('listings.0.category.name', 'Music')
            ->where('listings.0.promoter.name', 'Emergence Restock')
            ->where('listings.0.ticket_price', 'Free')
        );
});

/**
 * The whole list is sent in one go rather than a page at a time, which is
 * what lets the filter work without going back to the server. Paginate
 * this and the filter quietly starts searching the first page only.
 */
test('the full listing is sent whole rather than a page at a time', function () {
    Listing::factory()->count(30)->create([
        'start_date' => today()->addWeek(),
    ]);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('listings', 30)
            ->missing('listings.links')
        );
});

/**
 * The filter sits on every listing view, so every one of them has to be
 * given the promoter - a search that silently matched nothing on Today
 * would look like the promoter having no events on.
 */
test('every listing view carries the promoter the filter searches by', function () {
    $promoter = Promoter::factory()->create(['name' => 'Just SUST']);
    $venue = Venue::factory()->create(['name' => 'The Sub Rooms']);

    Listing::factory()->create([
        'promoter_id' => $promoter->id,
        'venue_id' => $venue->id,
        'start_date' => today(),
    ]);

    $routes = [
        route('today'),
        route('ThisWeek'),
        route('Listings'),
        route('Venue', ['id' => $venue->id, 'slug' => 'the-sub-rooms']),
    ];

    foreach ($routes as $route) {
        $this->get($route)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('listings.0.promoter.name', 'Just SUST'));
    }
});
