<?php

use App\Models\Listing;
use App\Models\User;
use App\Models\Venue;

const SUPPORT_LINK = '[data-sidebar="footer"] a[href^="mailto:"]';

test('the profile is reached from the name in the sidebar, not a link of its own', function () {
    $this->actingAs(User::factory()->create(['is_promoter' => true]));

    visit('/dashboard')
        ->assertPresent('[data-test="sidebar-user"] a[href$="/settings/profile"]')
        ->assertNotPresent('[data-sidebar="footer"] [data-sidebar="group"] a[href$="/settings/profile"]')
        ->assertNoJavaScriptErrors();
});

test('Support is offered to promoters, venue managers and staff', function (array $attributes) {
    $this->actingAs(User::factory()->create($attributes));

    visit('/dashboard')
        ->assertPresent(SUPPORT_LINK)
        ->assertNoJavaScriptErrors();
})->with([
    'a promoter' => [['is_promoter' => true]],
    'a venue manager' => [['is_venue_mgr' => true]],
    'staff' => [['email' => 'robin@ultrasimplified.com']],
]);

test('Support is offered to somebody who runs a venue without the role', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create();
    $this->actingAs($user);

    visit(route('dashboard.listings.index', ['venue' => $venue->id], false))
        ->assertPresent(SUPPORT_LINK)
        ->assertNoJavaScriptErrors();
});

test('Support is not offered to a writer, or to somebody who only keeps bookmarks', function (bool $isWriter) {
    $user = User::factory()->create(['is_writer' => $isWriter]);
    $user->bookmarks()->attach(Listing::factory()->create(['start_date' => today()->addDay()]));
    $this->actingAs($user);

    visit('/dashboard')
        ->assertPresent('[data-test="sidebar-user"]')
        ->assertNotPresent(SUPPORT_LINK)
        ->assertNoJavaScriptErrors();
})->with(['a writer' => true, 'a reader' => false]);
