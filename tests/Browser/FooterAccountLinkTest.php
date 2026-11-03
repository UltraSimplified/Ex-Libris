<?php

use App\Models\Listing;
use App\Models\User;

/*
 * The footer's own Account link. Found by where it goes rather than its
 * words, which the phone menu's Account link shares: that one comes first
 * on the page and is hidden on a desktop.
 */
const FOOTER_ACCOUNT = '.site-footer a[href="/dashboard/"]';

/**
 * An account that manages no venue, promotes nothing and writes nothing
 * still has a dashboard, and logs out from there rather than the footer.
 */
test('a reader\'s Account link goes to the dashboard, where they can log out', function () {
    $reader = User::factory()->create(['name' => 'Sam Reader']);
    $this->actingAs($reader);

    visit('/listings')
        ->assertSeeLink(FOOTER_ACCOUNT)
        ->assertDontSee('Log out Sam Reader')
        ->click(FOOTER_ACCOUNT)
        ->assertPathIs('/dashboard/')
        ->click('Log out')
        ->assertPathIs('/')
        ->assertSeeLink(FOOTER_ACCOUNT)
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
});

test('an account with a dashboard keeps its Account link', function (array $role) {
    $this->actingAs(User::factory()->create(['name' => 'Pat Promoter', ...$role]));

    visit('/listings')
        ->assertSeeLink(FOOTER_ACCOUNT)
        ->assertDontSee('Log out Pat Promoter')
        ->assertNoJavaScriptErrors();
})->with([
    'venue manager' => [['is_venue_mgr' => true]],
    'promoter' => [['is_promoter' => true]],
    'writer' => [['is_writer' => true]],
    'staff' => [['email' => 'robin@ultrasimplified.com']],
]);

test('somebody not signed in sees Account', function () {
    visit('/listings')
        ->assertSeeLink(FOOTER_ACCOUNT)
        ->assertNoJavaScriptErrors();
});

/*
 * Somebody with a saved event still to come has the Dashboard, so they get
 * the way there, and sign out from its sidebar.
 */
test('a reader with an event still to come keeps their Account link', function () {
    $reader = User::factory()->create(['name' => 'Sam Reader']);
    $reader->bookmarks()->attach(Listing::factory()->create(['start_date' => today()->addDay()]));
    $this->actingAs($reader);

    visit('/listings')
        ->assertSeeLink(FOOTER_ACCOUNT)
        ->assertDontSee('Log out Sam Reader')
        ->assertNoJavaScriptErrors();
});
