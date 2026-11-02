<?php

use App\Models\Listing;
use App\Models\User;
use App\Services\Bookmarks\GuestBookmarks;

/**
 * Whether the bookmarks table has this account going to this event.
 */
function isAttending(User $user, Listing $listing): bool
{
    return (bool) $user->bookmarks()->whereKey($listing->id)->first()->pivot->attending;
}

test('ticking and unticking are recorded on the bookmark', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();
    $user->bookmarks()->attach($listing);

    $this->actingAs($user)
        ->putJson(route('listings.attending.store', $listing))
        ->assertOk()
        ->assertExactJson(['attending' => true]);

    expect(isAttending($user, $listing))->toBeTrue();

    $this->actingAs($user)
        ->deleteJson(route('listings.attending.destroy', $listing))
        ->assertOk()
        ->assertExactJson(['attending' => false]);

    expect(isAttending($user, $listing))->toBeFalse();
});

test('asking twice gets the same answer both times', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();
    $user->bookmarks()->attach($listing, ['attending' => true]);

    $this->actingAs($user)
        ->putJson(route('listings.attending.store', $listing))
        ->assertOk()
        ->assertExactJson(['attending' => true]);

    expect(isAttending($user, $listing))->toBeTrue();
});

test('a listing the account has not saved cannot be ticked', function (string $method, string $route) {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    $this->actingAs($user)
        ->json($method, route($route, $listing))
        ->assertNotFound();

    $this->assertDatabaseCount('bookmarks', 0);
})->with([
    'ticking' => ['PUT', 'listings.attending.store'],
    'unticking' => ['DELETE', 'listings.attending.destroy'],
]);

test('a guest cannot tick anything', function () {
    $this->putJson(route('listings.attending.store', Listing::factory()->create()))
        ->assertUnauthorized();
});

test('one account\'s tick leaves another\'s bookmark alone', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    $listing = Listing::factory()->create();
    $mine->bookmarks()->attach($listing);
    $theirs->bookmarks()->attach($listing);

    $this->actingAs($mine)
        ->putJson(route('listings.attending.store', $listing))
        ->assertOk();

    expect(isAttending($theirs, $listing))->toBeFalse();
});

test('saving an event again keeps its tick', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();
    $user->bookmarks()->attach($listing, ['attending' => true]);

    $this->actingAs($user)
        ->postJson(route('listings.bookmark.store', $listing))
        ->assertOk();

    expect(isAttending($user, $listing))->toBeTrue();
});

test('the tick goes with the bookmark', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();
    $user->bookmarks()->attach($listing, ['attending' => true]);

    $this->actingAs($user)->deleteJson(route('listings.bookmark.destroy', $listing))->assertOk();
    $this->actingAs($user)->postJson(route('listings.bookmark.store', $listing))->assertOk();

    expect(isAttending($user, $listing))->toBeFalse();
});

test('bringing in a browser\'s bookmarks on signing in keeps a tick', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();
    $user->bookmarks()->attach($listing, ['attending' => true]);

    $this->actingAs($user)
        ->withUnencryptedCookie(GuestBookmarks::COOKIE, (string) $listing->id)
        ->get('/listings')
        ->assertOk();

    expect(isAttending($user, $listing))->toBeTrue();
});
