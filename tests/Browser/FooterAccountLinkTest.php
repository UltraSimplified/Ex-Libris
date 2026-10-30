<?php

use App\Models\User;

/*
 * The footer's own Account link. Found by where it goes rather than its
 * words, which the phone menu's Account link shares: that one comes first
 * on the page and is hidden on a desktop.
 */
const FOOTER_ACCOUNT = '.site-footer a[href="/dashboard/"]';

/**
 * An account that manages no venue, promotes nothing and writes nothing
 * has no dashboard yet, so the footer's Account link signs it out instead.
 */
test('a reader\'s Account link logs them out', function () {
    $reader = User::factory()->create(['name' => 'Sam Reader']);
    $this->actingAs($reader);

    visit('/listings')
        ->assertDontSeeLink(FOOTER_ACCOUNT)
        ->click('Log out Sam Reader')
        ->assertSeeLink(FOOTER_ACCOUNT)
        ->assertDontSee('Log out Sam Reader')
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
