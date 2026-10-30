<?php

use App\Models\Listing;

test('the listings subnav marks the page it is on', function () {
    Listing::factory()->create(['title' => 'Village Fete', 'start_date' => today()->toDateString(), 'end_date' => null]);

    visit('/listings')
        ->assertSeeIn('a[href="/listings/"] strong', 'ALL')
        ->assertMissing('a[href="/listings/today"] strong')
        ->click('a[href="/listings/today"]')
        ->assertPathIs('/listings/today')
        ->assertSee('Village Fete')
        ->assertSeeIn('a[href="/listings/today"] strong', 'TODAY')
        ->assertMissing('a[href="/listings/"] strong')
        ->click('a[href="/listings/"]')
        ->assertPathIs('/listings/')
        ->assertSeeIn('a[href="/listings/"] strong', 'ALL')
        ->assertNoJavaScriptErrors();
});
