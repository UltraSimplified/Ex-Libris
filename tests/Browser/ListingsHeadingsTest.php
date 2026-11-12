<?php

use App\Models\Listing;
use App\Models\User;

/*
 * The headings over the listings, in order.
 */
const LISTING_HEADINGS = <<<'JS'
    [...document.querySelectorAll('.listing-heading')]
        .map((heading) => heading.textContent.trim())
        .join(' | ')
    JS;

/*
 * The rows of the listings grid in order, each a heading's words or a
 * listing's title.
 */
const HEADED_ROWS = <<<'JS'
    [...document.querySelector('.divide-silver').children]
        .map((row) => (row.querySelector('.listing-heading') ?? row.querySelector('h1')).textContent.trim())
        .join(' | ')
    JS;

/**
 * The headings are laid out in the page, among the rows the reader can
 * search, narrow by genre and unbookmark there, so only a browser sees
 * them. The server's today reaches the page with the listings, so moving
 * the clock here moves the page's today with it.
 */
test('each of the next few days is headed above its own listings, and a day with nothing on is not', function () {
    $this->travelTo('2026-11-12 12:00');
    Listing::factory()->create(['title' => 'Tonight', 'start_date' => '2026-11-12']);
    Listing::factory()->create(['title' => 'Friday Night', 'start_date' => '2026-11-13']);
    Listing::factory()->create(['title' => 'Saturday Matinee', 'start_date' => '2026-11-14']);
    Listing::factory()->create(['title' => 'Monday Club', 'start_date' => '2026-11-16']);

    visit('/listings')
        ->assertScript(HEADED_ROWS, implode(' | ', [
            'TODAY', 'Tonight',
            'TOMORROW', 'Friday Night',
            'SATURDAY 14 NOV', 'Saturday Matinee',
            'MONDAY 16 NOV', 'Monday Club',
        ]))
        ->assertNoJavaScriptErrors();
});

test('a day in the new year is headed with its year', function () {
    $this->travelTo('2026-12-20 12:00');
    Listing::factory()->create(['start_date' => '2027-01-09']);

    visit('/listings')
        ->assertScript(LISTING_HEADINGS, 'SATURDAY 9 JAN 27')
        ->assertNoJavaScriptErrors();
});

test('past the end of next month, a month is headed as a whole', function () {
    $this->travelTo('2026-11-12 12:00');
    Listing::factory()->create(['title' => 'Hogmanay', 'start_date' => '2026-12-31']);
    Listing::factory()->create(['title' => 'Twelfth Night', 'start_date' => '2027-01-06']);
    Listing::factory()->create(['title' => 'Burns Supper', 'start_date' => '2027-01-20']);

    visit('/listings')
        ->assertScript(HEADED_ROWS, implode(' | ', [
            'THURSDAY 31 DEC', 'Hogmanay',
            'JANUARY 2027', 'Twelfth Night', 'Burns Supper',
        ]))
        ->assertNoJavaScriptErrors();
});

test('a month later this year is headed without the year', function () {
    $this->travelTo('2027-03-10 12:00');
    Listing::factory()->create(['start_date' => '2027-05-03']);

    visit('/listings')
        ->assertScript(LISTING_HEADINGS, 'MAY')
        ->assertNoJavaScriptErrors();
});

test('a chosen range far ahead is headed by day, counting from its own first day', function () {
    $this->travelTo('2026-11-12 12:00');
    Listing::factory()->create(['start_date' => '2027-03-01']);
    Listing::factory()->create(['start_date' => '2027-03-03']);

    visit('/listings/dates/2027-03-01/2027-03-07')
        ->assertScript(LISTING_HEADINGS, 'MONDAY 1 MAR 27 | WEDNESDAY 3 MAR 27')
        ->assertNoJavaScriptErrors();
});

test('a page about a single day has no headings', function (string $path) {
    $this->travelTo('2026-11-12 12:00');
    Listing::factory()->create(['title' => 'Tonight', 'start_date' => '2026-11-12']);

    visit($path)
        ->assertSee('Tonight')
        ->assertCount('.listing-heading', 0)
        ->assertNoJavaScriptErrors();
})->with([
    'the Today page' => '/listings/today',
    'a one-day range' => '/listings/dates/2026-11-12/2026-11-12',
]);

test('a search leaves only the headings of days with something that matches', function () {
    $this->travelTo('2026-11-12 12:00');
    Listing::factory()->create(['title' => 'Village Fete', 'start_date' => '2026-11-12']);
    Listing::factory()->create(['title' => 'Late Night Jazz', 'start_date' => '2026-11-14']);

    visit('/listings')
        ->click('[aria-controls="listing-search-panel"]')
        ->fill('#listing-search', 'jazz')
        ->wait(0.5)
        ->assertScript(HEADED_ROWS, 'SATURDAY 14 NOV | Late Night Jazz')
        ->assertNoJavaScriptErrors();
});

test('emptying the last heart of a day on My Bookmarks takes its heading too', function () {
    $this->travelTo('2026-11-12 12:00');
    $reader = User::factory()->create();
    $reader->bookmarks()->attach([
        Listing::factory()->create(['title' => 'Friday Night', 'start_date' => '2026-11-13'])->id,
        Listing::factory()->create(['title' => 'Saturday Matinee', 'start_date' => '2026-11-14'])->id,
    ]);
    $this->actingAs($reader);

    visit('/listings/bookmarked/')
        ->assertScript(LISTING_HEADINGS, 'TOMORROW | SATURDAY 14 NOV')
        ->click('[aria-label="Click to remove this bookmark"] >> nth=0')
        ->wait(1)
        ->assertScript(HEADED_ROWS, 'SATURDAY 14 NOV | Saturday Matinee')
        ->assertNoJavaScriptErrors();
});
