<?php

use App\Models\Festival;
use App\Models\Listing;
use App\Models\Promoter;
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

/*
 * Festivals hang off the promoter that puts them on, and each one has a
 * section of its own, for an account the office has let run festivals.
 */
test('a promoter with a festival has Festivals under it and a section for the festival', function () {
    $user = User::factory()->runsFestivals()->create();
    $promoter = Promoter::factory()->for($user)->create(['name' => 'Northgate Promotions']);
    $festival = Festival::factory()->for($promoter)->create(['name' => 'Riverside Fest 2027']);
    $this->actingAs($user);

    visit('/dashboard')
        ->assertPresent('[data-sidebar="content"] a[href$="/dashboard/festivals?promoter='.$promoter->id.'"]')
        ->assertSeeIn('[data-sidebar="content"]', 'Riverside Fest 2027')
        ->assertPresent('[data-sidebar="content"] a[href$="/dashboard/listings?festival='.$festival->id.'"]')
        ->assertPresent('[data-sidebar="content"] a[href$="/dashboard/widgets?festival='.$festival->id.'"]')
        ->assertNoJavaScriptErrors();
});

test('a promoter with no festivals still has Festivals under it, and no festival sections', function () {
    $user = User::factory()->runsFestivals()->create();
    $promoter = Promoter::factory()->for($user)->create();
    $this->actingAs($user);

    visit('/dashboard')
        ->assertPresent('[data-sidebar="content"] a[href$="/dashboard/festivals?promoter='.$promoter->id.'"]')
        ->assertNotPresent('[data-sidebar="content"] a[href*="festival="]')
        ->assertNoJavaScriptErrors();
});

test('a promoter without Runs Festivals sees nothing of festivals in the menu', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create();
    Festival::factory()->for($promoter)->create(['name' => 'Riverside Fest 2027']);
    $this->actingAs($user);

    visit('/dashboard')
        ->assertNotPresent('[data-sidebar="content"] a[href*="/dashboard/festivals"]')
        ->assertNotPresent('[data-sidebar="content"] a[href*="festival="]')
        ->assertDontSeeIn('[data-sidebar="content"]', 'Riverside Fest 2027')
        ->assertNoJavaScriptErrors();
});
