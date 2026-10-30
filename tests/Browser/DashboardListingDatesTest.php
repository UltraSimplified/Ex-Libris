<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;

/**
 * A date that was saved opens again as the day before.
 *
 * A listing's dates are days, but they went to the browser as the instant
 * that day begins in London. In summer that is an hour before midnight in
 * UTC - 15 July became 2026-07-14T23:00:00Z - and the form, which takes the
 * first ten characters of whatever it is given, showed the 14th. Saving it
 * again would have kept the 14th, so each trip through the form cost a day.
 *
 * Driven through the real form both ways, since the shift happens between
 * what the server sends and what the date field shows.
 */
test('the dates saved on a listing are the dates it opens with', function () {
    $user = User::factory()->agreed()->create();
    Promoter::factory()->create(['user_id' => $user->id]);

    $category = Category::factory()->create();

    $this->actingAs($user);

    // A summer date, when London is an hour ahead of UTC. The same day for
    // both ends, which is what a one-night event is.
    visit(route('dashboard.listings.create', absolute: false))
        ->fill('#title', 'TEST ENTRY')
        ->select('#category_id', (string) $category->id)
        ->fill('#start_date', '15/07/2026')
        ->fill('#end_date', '15/07/2026')
        ->fill('#body', 'TEST ENTRY')
        ->press('Create listing')
        ->assertPathIs('/dashboard/listings');

    $listing = Listing::sole();

    visit(route('dashboard.listings.edit', $listing, absolute: false))
        ->assertValue('#start_date', '15/07/2026')
        ->assertValue('#end_date', '15/07/2026')
        ->assertNoJavaScriptErrors();
});

/**
 * The date fields are text, read as day/month/year whatever language the
 * browser is set to, so an owner set to American English no longer gets
 * month/day/year. Typed shorthand is written out in full once they move on.
 */
test('a date is typed day first and written out in full', function () {
    $user = User::factory()->agreed()->create();
    Promoter::factory()->create(['user_id' => $user->id]);

    $category = Category::factory()->create();

    $this->actingAs($user);

    visit(route('dashboard.listings.create', absolute: false))
        ->fill('#title', 'TEST ENTRY')
        ->select('#category_id', (string) $category->id)
        ->fill('#start_date', '3.8.26')
        ->click('#title')
        ->assertValue('#start_date', '03/08/2026')
        ->fill('#body', 'TEST ENTRY')
        ->press('Create listing')
        ->assertPathIs('/dashboard/listings');

    expect(Listing::sole()->start_date->toDateString())->toBe('2026-08-03');
});

test('something that is not a day/month/year date is not sent', function () {
    $user = User::factory()->agreed()->create();
    Promoter::factory()->create(['user_id' => $user->id]);

    $category = Category::factory()->create();

    $this->actingAs($user);

    visit(route('dashboard.listings.create', absolute: false))
        ->fill('#title', 'TEST ENTRY')
        ->select('#category_id', (string) $category->id)
        ->fill('#start_date', '07/31/2026')
        ->fill('#body', 'TEST ENTRY')
        ->press('Create listing')
        ->assertPathIs('/dashboard/listings/create')
        ->assertAttribute('#start_date', 'aria-invalid', 'true');

    expect(Listing::count())->toBe(0);
});
