<?php

use App\Models\Listing;
use App\Models\User;
use App\Services\Bookmarks\GuestBookmarks;
use Illuminate\Database\Eloquent\Collection;

/**
 * Events still to come, as somebody browsing the listings could save them.
 *
 * @return Collection<int, Listing>
 */
function upcomingForGuests(int $count): Collection
{
    return Listing::factory()->count($count)->create(['start_date' => today()->addDay()]);
}

/**
 * The cookie a browser holding these listings would send. The JSON calls
 * ask for it to go withCredentials, as the page's own fetch does; without
 * that, the test client sends a JSON request no cookies at all.
 */
function guestCookieFor(Listing ...$listings): string
{
    return collect($listings)->pluck('id')->implode('.');
}

test('a guest\'s bookmarks build up in the order they were saved', function () {
    [$first, $second] = upcomingForGuests(2);

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, guestCookieFor($first))
        ->withCredentials()
        ->postJson(route('listings.bookmark.store', $second))
        ->assertOk()
        ->assertJson(['bookmarked' => true, 'count' => 2])
        ->assertPlainCookie(GuestBookmarks::COOKIE, guestCookieFor($first, $second));
});

test('a guest bookmarking the same event twice keeps it once', function () {
    [$listing] = upcomingForGuests(1);

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, guestCookieFor($listing))
        ->withCredentials()
        ->postJson(route('listings.bookmark.store', $listing))
        ->assertOk()
        ->assertJson(['bookmarked' => true, 'count' => 1])
        ->assertPlainCookie(GuestBookmarks::COOKIE, guestCookieFor($listing));
});

test('a guest can remove a bookmark', function () {
    [$first, $second] = upcomingForGuests(2);

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, guestCookieFor($first, $second))
        ->withCredentials()
        ->deleteJson(route('listings.bookmark.destroy', $first))
        ->assertOk()
        ->assertJson(['bookmarked' => false, 'count' => 1])
        ->assertPlainCookie(GuestBookmarks::COOKIE, guestCookieFor($second));
});

test('removing a guest\'s last bookmark clears the cookie', function () {
    [$listing] = upcomingForGuests(1);

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, guestCookieFor($listing))
        ->withCredentials()
        ->deleteJson(route('listings.bookmark.destroy', $listing))
        ->assertOk()
        ->assertJson(['bookmarked' => false, 'count' => 0])
        ->assertCookieExpired(GuestBookmarks::COOKIE);
});

test('a guest keeps the newest hundred bookmarks', function () {
    $listings = upcomingForGuests(GuestBookmarks::LIMIT + 1);
    $saved = $listings->take(GuestBookmarks::LIMIT);

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, guestCookieFor(...$saved))
        ->withCredentials()
        ->postJson(route('listings.bookmark.store', $listings->last()))
        ->assertOk()
        ->assertJson(['count' => GuestBookmarks::LIMIT])
        ->assertPlainCookie(GuestBookmarks::COOKIE, guestCookieFor(...$listings->skip(1)));
});

test('anything in the cookie that is not a listing id is ignored', function () {
    [$listing] = upcomingForGuests(1);

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, "abc.{$listing->id}.-4.0.1e3.<b>")
        ->get('/listings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('bookmarks', [$listing->id]));
});

test('finished and deleted events drop out of a guest\'s cookie', function () {
    [$current] = upcomingForGuests(1);
    $finished = Listing::factory()->create(['start_date' => today()->subWeek(), 'end_date' => today()->subDays(2)]);
    $deleted = Listing::factory()->create(['start_date' => today()->addDay()]);
    $cookie = guestCookieFor($finished, $current, $deleted);
    $deleted->delete();

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, $cookie)
        ->get('/listings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('bookmarks', [$current->id]))
        ->assertPlainCookie(GuestBookmarks::COOKIE, guestCookieFor($current));
});

test('a guest with nothing saved is sent no cookie', function () {
    $this->get('/listings')
        ->assertOk()
        ->assertCookieMissing(GuestBookmarks::COOKIE);
});

test('a guest\'s bookmarked events page lists what their browser saved, soonest on first', function () {
    $later = Listing::factory()->create(['start_date' => today()->addWeek()]);
    $sooner = Listing::factory()->create(['start_date' => today()->addDay()]);
    upcomingForGuests(1);

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, guestCookieFor($later, $sooner))
        ->get(route('listings.bookmarked'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Listings')
            ->where('bookmarked', true)
            ->has('listings', 2)
            ->where('listings.0.id', $sooner->id)
            ->where('listings.1.id', $later->id)
        );
});

test('bookmarks saved before logging in move onto the account', function () {
    $user = User::factory()->create();
    [$before, $both] = upcomingForGuests(2);
    $user->bookmarks()->attach($both);

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, guestCookieFor($before, $both))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->get('/listings')
        ->assertOk()
        ->assertCookieExpired(GuestBookmarks::COOKIE)
        ->assertInertia(fn ($page) => $page->where('bookmarks', fn ($ids) => collect($ids)->sort()->values()->all() === collect([$before->id, $both->id])->sort()->values()->all()));

    expect($user->bookmarks()->pluck('listings.id')->sort()->values()->all())
        ->toBe(collect([$before->id, $both->id])->sort()->values()->all());
    $this->assertDatabaseCount('bookmarks', 2);
});

test('bookmarks saved before registering move onto the new account', function () {
    [$listing] = upcomingForGuests(1);

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, guestCookieFor($listing))
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $this->get('/listings')->assertOk()->assertCookieExpired(GuestBookmarks::COOKIE);

    expect(User::where('email', 'test@example.com')->sole()->bookmarks()->pluck('listings.id')->all())
        ->toBe([$listing->id]);
});

test('a signed-in visitor\'s bookmarks go to the account, not a cookie', function () {
    $user = User::factory()->create();
    [$listing] = upcomingForGuests(1);

    $this->actingAs($user)
        ->postJson(route('listings.bookmark.store', $listing))
        ->assertOk()
        ->assertCookieMissing(GuestBookmarks::COOKIE);

    expect($user->bookmarks()->pluck('listings.id')->all())->toBe([$listing->id]);
});
