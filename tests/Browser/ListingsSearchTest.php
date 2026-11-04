<?php

use App\Models\Listing;
use App\Models\Venue;
use App\Services\Listings\ListingLineUp;

/**
 * The filter runs in the page, over a list that has already arrived, so
 * what it matches can only be seen by typing into it.
 */
test('a search ignores accents whichever way round they are', function (string $typed) {
    Listing::factory()->create(['title' => 'Très Béret', 'start_date' => today()->addDay()]);
    Listing::factory()->create(['title' => 'Tres Bien Quiz', 'start_date' => today()->addDay()]);
    Listing::factory()->create(['title' => 'Village Fete', 'start_date' => today()->addDay()]);

    visit('/listings')
        ->click('[aria-controls="listing-search-panel"]')
        ->fill('#listing-search', $typed)
        ->wait(0.5)
        ->assertSee('Très Béret')
        ->assertSee('Tres Bien Quiz')
        ->assertDontSee('Village Fete')
        ->assertNoJavaScriptErrors();
})->with(['without the accent' => 'tres', 'with the accent' => 'très']);

test('a search finds the events at venues in a town', function () {
    Listing::factory()->create([
        'title' => 'Late Night Jazz',
        'venue_id' => Venue::factory()->create(['name' => 'Ruskin Mill', 'town' => 'Nailsworth'])->id,
        'start_date' => today()->addDay(),
    ]);
    Listing::factory()->create([
        'title' => 'Village Fete',
        'venue_id' => Venue::factory()->create(['name' => 'The Sub Rooms', 'town' => 'Stroud'])->id,
        'start_date' => today()->addDay(),
    ]);

    visit('/listings')
        ->click('[aria-controls="listing-search-panel"]')
        ->fill('#listing-search', 'nailsworth')
        ->wait(0.5)
        ->assertSee('Late Night Jazz')
        ->assertDontSee('Village Fete')
        ->assertNoJavaScriptErrors();
});

/**
 * Plenty of venues are down under Stroud with the place they are really in
 * only in the address, so the town alone would not find them.
 */
test('a search finds the events at a venue by its address or postcode', function (string $typed) {
    Listing::factory()->create([
        'title' => 'Late Night Jazz',
        'venue_id' => Venue::factory()->create([
            'name' => 'Stroud Brewery',
            'town' => 'Stroud',
            'address' => 'London Rd, Brimscombe',
            'postcode' => 'GL5 2BY',
        ])->id,
        'start_date' => today()->addDay(),
    ]);
    Listing::factory()->create([
        'title' => 'Village Fete',
        'venue_id' => Venue::factory()->create([
            'name' => 'The Sub Rooms',
            'town' => 'Stroud',
            'address' => 'George Street',
            'postcode' => 'GL5 1AE',
        ])->id,
        'start_date' => today()->addDay(),
    ]);

    visit('/listings')
        ->click('[aria-controls="listing-search-panel"]')
        ->fill('#listing-search', $typed)
        ->wait(0.5)
        ->assertSee('Late Night Jazz')
        ->assertDontSee('Village Fete')
        ->assertNoJavaScriptErrors();
})->with(['the address' => 'brimscombe', 'the postcode' => 'gl5 2by']);

test('a search finds an event by an artist on its line-up', function () {
    Listing::factory()->create([
        'title' => 'Sound Records Showcase',
        'line_up' => app(ListingLineUp::class)->fromSingleEmbeds('Jackson Almond', null, 'https://youtu.be/abc123'),
        'start_date' => today()->addDay(),
    ]);
    Listing::factory()->create(['title' => 'Village Fete', 'line_up' => null, 'start_date' => today()->addDay()]);

    visit('/listings')
        ->click('[aria-controls="listing-search-panel"]')
        ->fill('#listing-search', 'jackson almond')
        ->wait(0.5)
        ->assertSee('Sound Records Showcase')
        ->assertDontSee('Village Fete')
        ->assertNoJavaScriptErrors();
});

/**
 * Phones type a curly apostrophe and keyboards a straight one, the listings
 * hold both, and plenty of people leave it out altogether.
 */
test('a search ignores apostrophes however they are typed', function (string $typed) {
    Listing::factory()->create(['title' => 'Tomorrow’s Warriors', 'start_date' => today()->addDay()]);
    Listing::factory()->create(['title' => "Tomorrow's Child", 'start_date' => today()->addDay()]);
    Listing::factory()->create(['title' => 'Village Fete', 'start_date' => today()->addDay()]);

    visit('/listings')
        ->click('[aria-controls="listing-search-panel"]')
        ->fill('#listing-search', $typed)
        ->wait(0.5)
        ->assertSee('Tomorrow’s Warriors')
        ->assertSee("Tomorrow's Child")
        ->assertDontSee('Village Fete')
        ->assertNoJavaScriptErrors();
})->with(['straight' => "tomorrow's", 'curly' => 'tomorrow’s', 'left out' => 'tomorrows']);
