<?php

use App\Models\Listing;

test('a guest can bookmark an event and find it again on a later visit', function () {
    Listing::factory()->create(['title' => 'Village Fete', 'start_date' => today()->addDay()]);

    $page = visit('/listings')
        ->assertDontSee('MY BOOKMARKS')
        ->click('[aria-label="Click to bookmark this listing"]')
        ->waitForText('MY BOOKMARKS')
        ->assertAttribute('[aria-label="Click to remove this bookmark"]', 'aria-pressed', 'true');

    $page->navigate('/listings')
        ->assertAttribute('[aria-label="Click to remove this bookmark"]', 'aria-pressed', 'true')
        ->click('a[href="/listings/bookmarked/"]')
        ->assertPathIs('/listings/bookmarked/')
        ->assertSee('Village Fete')
        ->assertSee('SAVED IN THIS BROWSER.')
        ->assertNoJavaScriptErrors();
});
