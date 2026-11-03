<?php

use App\Models\Listing;
use App\Models\User;

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

test('removing a bookmark on My Bookmarks takes its row away, and the last says none are left', function () {
    $user = User::factory()->create();
    $user->bookmarks()->attach([
        Listing::factory()->create(['title' => 'Village Fete', 'start_date' => today()->addDay()])->id,
        Listing::factory()->create(['title' => 'Harvest Supper', 'start_date' => today()->addDays(2)])->id,
    ]);
    $this->actingAs($user);

    visit('/listings/bookmarked/')
        ->assertSee('Village Fete')
        ->assertSee('Harvest Supper')
        ->click('[aria-label="Click to remove this bookmark"] >> nth=0')
        ->wait(0.5)
        ->assertDontSee('Village Fete')
        ->assertSee('Harvest Supper')
        ->click('[aria-label="Click to remove this bookmark"] >> nth=0')
        ->wait(0.5)
        ->assertDontSee('Harvest Supper')
        ->assertSee('NO BOOKMARKED EVENTS')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('bookmarks', 0);
});
