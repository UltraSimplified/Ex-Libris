<?php

use App\Models\User;

const PUBLISHER_PANEL = '[data-test="publisher-panel"]';

/**
 * A publisher's Backstage opens on the account as a publisher, laid out
 * like a promoter's panel but with no logo or count, in place of the plain
 * account panel.
 */
test('a publisher sees a Publisher panel in place of the account panel', function () {
    $this->actingAs(User::factory()->publisher()->create([
        'name' => 'TEST ENTRY Gig Guide',
        'email' => 'test-entry-publisher@example.com',
    ]));

    visit('/dashboard')
        ->assertSeeIn(PUBLISHER_PANEL, 'PUBLISHER')
        ->assertSeeIn(PUBLISHER_PANEL, 'TEST ENTRY Gig Guide')
        ->assertPresent(PUBLISHER_PANEL.' a[href$="/settings/profile"]')
        ->assertPresent(PUBLISHER_PANEL.' a[href="/dashboard/widgets?bookmarks=1"]')
        ->assertNotPresent(PUBLISHER_PANEL.' img')
        ->assertDontSee('Manage profile')
        ->assertDontSee('test-entry-publisher@example.com')
        ->click('Manage your website listing widgets')
        ->assertPathIs('/dashboard/widgets')
        ->assertQueryStringHas('bookmarks', '1')
        ->assertNoJavaScriptErrors();
});

test('anybody else keeps the account panel', function () {
    $this->actingAs(User::factory()->create(['is_promoter' => true]));

    visit('/dashboard')
        ->assertNotPresent(PUBLISHER_PANEL)
        ->assertSee('Manage profile')
        ->assertNoJavaScriptErrors();
});
