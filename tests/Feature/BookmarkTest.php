<?php

use App\Models\Listing;
use App\Models\User;
use App\Services\Bookmarks\GuestBookmarks;
use Illuminate\Support\Facades\Artisan;

test('a guest\'s bookmark is kept in their browser rather than the table', function () {
    $listing = Listing::factory()->create(['start_date' => today()->addDay()]);

    $this->postJson(route('listings.bookmark.store', $listing))
        ->assertOk()
        ->assertJson(['bookmarked' => true, 'count' => 1])
        ->assertPlainCookie(GuestBookmarks::COOKIE, (string) $listing->id);

    $this->assertDatabaseCount('bookmarks', 0);
});

test('a user can bookmark a listing', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    $this->actingAs($user)
        ->postJson(route('listings.bookmark.store', $listing))
        ->assertOk()
        ->assertJson(['bookmarked' => true, 'count' => 1]);

    $this->assertDatabaseHas('bookmarks', [
        'user_id' => $user->id,
        'listing_id' => $listing->id,
    ]);
});

test('bookmarking twice does not duplicate the row', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    $this->actingAs($user)
        ->postJson(route('listings.bookmark.store', $listing))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('listings.bookmark.store', $listing))
        ->assertOk()
        ->assertJson(['bookmarked' => true, 'count' => 1]);

    $this->assertDatabaseCount('bookmarks', 1);
});

test('a user can remove a bookmark', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();
    $user->bookmarks()->attach($listing);

    $this->actingAs($user)
        ->deleteJson(route('listings.bookmark.destroy', $listing))
        ->assertOk()
        ->assertJson(['bookmarked' => false, 'count' => 0]);

    $this->assertDatabaseCount('bookmarks', 0);
});

test('removing a bookmark that is not there is not an error', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('listings.bookmark.destroy', $listing))
        ->assertOk()
        ->assertJson(['bookmarked' => false, 'count' => 0]);
});

test('a bookmark belongs to the user who made it', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    $listing = Listing::factory()->create();

    $this->actingAs($mine)
        ->postJson(route('listings.bookmark.store', $listing))
        ->assertOk();

    expect($theirs->bookmarks()->count())->toBe(0);
});

test('the saved ids are shared with every page', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();
    $user->bookmarks()->attach($listing);

    $this->actingAs($user)
        ->get('/listings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('bookmarks', [$listing->id])
        );
});

test('a guest with nothing saved is told about no bookmarks at all', function () {
    $this->get('/listings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('bookmarks', []));
});

test('the bookmarked events page lists only what this user saved', function () {
    $user = User::factory()->create();
    $mine = Listing::factory()->create(['start_date' => today()->addDay()]);
    $theirs = Listing::factory()->create(['start_date' => today()->addDay()]);

    $user->bookmarks()->attach($mine);
    User::factory()->create()->bookmarks()->attach($theirs);

    $this->actingAs($user)
        ->get(route('listings.bookmarked'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Listings')
            ->where('bookmarked', true)
            ->has('listings', 1)
            ->where('listings.0.id', $mine->id)
        );
});

test('a guest with nothing saved finds the bookmarked events page empty', function () {
    $this->get(route('listings.bookmarked'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Listings')
            ->where('bookmarked', true)
            ->has('listings', 0)
        );
});

test('the dashboard shows the saved events', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->create(['start_date' => today()->addDay()]);
    $user->bookmarks()->attach($listing);

    $this->actingAs($user)
        ->get(route('dashboard.bookmarks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/bookmarks/Index')
            ->has('listings', 1)
            ->where('listings.0.id', $listing->id)
        );
});

test('the prune command only reports until it is told to commit', function () {
    $user = User::factory()->create();
    $gone = Listing::factory()->create([
        'start_date' => today()->subWeek(),
        'end_date' => today()->subDays(3),
    ]);
    $user->bookmarks()->attach($gone);

    Artisan::call('bookmarks:prune');
    $this->assertDatabaseCount('bookmarks', 1);

    Artisan::call('bookmarks:prune', ['--commit' => true]);
    $this->assertDatabaseCount('bookmarks', 0);
});

test('pruning leaves bookmarks for events still to come', function () {
    $user = User::factory()->create();
    $today = Listing::factory()->create([
        'start_date' => today(),
        'end_date' => null,
    ]);
    $later = Listing::factory()->create(['start_date' => today()->addMonth()]);
    $user->bookmarks()->attach([$today->id, $later->id]);

    Artisan::call('bookmarks:prune', ['--commit' => true]);

    $this->assertDatabaseCount('bookmarks', 2);
});
