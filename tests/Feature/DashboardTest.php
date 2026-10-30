<?php

use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Models\Widget;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('an account with a role can visit the dashboard', function (string $role) {
    $this->actingAs(User::factory()->create([$role => true]))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
})->with(User::ROLES);

/**
 * Somebody who manages no venue, promotes nothing and writes nothing -
 * signed up to keep their bookmarks - has no dashboard yet.
 */
test('a reader is sent from the dashboard to the home page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('home'));
});

test('a reader who logs in lands on the home page', function () {
    $reader = User::factory()->create();

    $this->followingRedirects()
        ->post(route('login.store'), ['email' => $reader->email, 'password' => 'password'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

test('a reader who registers lands on the home page', function () {
    $this->followingRedirects()
        ->post(route('register.store'), [
            'name' => 'Sam Reader',
            'email' => 'sam@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

test('a promoter who logs in lands on the dashboard', function () {
    $promoter = User::factory()->create(['is_promoter' => true]);

    $this->followingRedirects()
        ->post(route('login.store'), ['email' => $promoter->email, 'password' => 'password'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

/**
 * Staff are recognised by the viewNova gate, which is an allowlist of
 * addresses in NovaServiceProvider - hence the real one here. Account
 * means the account page for them too; the office has its own door.
 */
test('staff get the account page like everybody else', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('staff can still see what a promoter sees', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    $this->actingAs($staff)
        ->get(route('dashboard.listings.index'))
        ->assertOk();
});

test('a promoter still gets the account page', function () {
    $this->actingAs(User::factory()->create(['is_promoter' => true]))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('staff are shown the way back to the office', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.office', '/office'));
});

test('everybody else is not told where the office is', function () {
    $this->actingAs(User::factory()->create(['is_promoter' => true]))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.office', null));
});

/**
 * The account page leads with the account holder's own face, so whatever
 * it draws that from has to reach the page. The avatar is stored as a
 * path on the Spaces disk and resolved against the CDN in the browser,
 * which needs both halves.
 */
test('the account page is given the face it leads with', function () {
    $user = User::factory()->create(['avatar' => 'avatars/whoever.webp', 'is_writer' => true]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.avatar', 'avatars/whoever.webp')
            ->where('auth.user.name', $user->name)
            ->has('cdnUrl')
        );
});

test('an account with no avatar is left to fall back to initials', function () {
    $user = User::factory()->create(['avatar' => null, 'is_writer' => true]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.user.avatar', null));
});

/**
 * The account page shows the account, then a panel for each venue and
 * promoter it runs, with how many of their events are on the site now.
 */
test('the account page carries the account and what it runs, by name', function () {
    $owner = User::factory()->create(['name' => 'Sam Owner', 'email' => 'sam@example.com', 'is_promoter' => true]);
    $prince = Venue::factory()->create(['name' => 'The Prince Albert', 'user_id' => $owner->id]);
    Venue::factory()->create(['name' => 'Ale House', 'user_id' => $owner->id]);
    Venue::factory()->create(['name' => 'Somebody Else\'s Hall']);
    $promoter = Promoter::factory()->create(['name' => 'The Yellow Lighted Bookshop', 'user_id' => $owner->id]);

    Listing::factory()->count(2)->create(['venue_id' => $prince->id, 'promoter_id' => $promoter->id]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('account', ['name' => 'Sam Owner', 'email' => 'sam@example.com'])
            ->where('venues.0.name', 'Ale House')
            ->where('venues.0.eventsListed', 0)
            ->where('venues.1.name', 'The Prince Albert')
            ->where('venues.1.eventsListed', 2)
            ->has('venues', 2)
            ->where('promoters', [['id' => $promoter->id, 'name' => 'The Yellow Lighted Bookshop', 'eventsListed' => 2, 'widgets' => 0, 'logo' => null]])
        );
});

test('only events still to come are counted', function () {
    $owner = User::factory()->create(['is_venue_mgr' => true]);
    $venue = Venue::factory()->create(['user_id' => $owner->id]);

    Listing::factory()->count(2)->create(['venue_id' => $venue->id]);
    Listing::factory()->create(['venue_id' => $venue->id, 'start_date' => today()->subWeek(), 'end_date' => today()->subDay()]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('venues.0.eventsListed', 2));
});

/*
 * Each panel offers the venue's or promoter's gig widgets: the list where
 * it has some, or a new one where it has none, so the page needs to know
 * which.
 */
test('the account page counts each venue\'s and promoter\'s gig widgets', function () {
    $owner = User::factory()->create(['is_promoter' => true]);
    $venue = Venue::factory()->create(['user_id' => $owner->id]);
    $promoter = Promoter::factory()->create(['user_id' => $owner->id]);

    Widget::factory()->count(2)->create(['owner_type' => Venue::class, 'owner_id' => $venue->id]);
    Widget::factory()->create();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('venues.0.widgets', 2)
            ->where('promoters.0.id', $promoter->id)
            ->where('promoters.0.widgets', 0));
});

test('the account page is given each promoter\'s logo for its disk', function () {
    $owner = User::factory()->create(['is_promoter' => true]);
    Promoter::factory()->create(['user_id' => $owner->id, 'logo' => 'promoters/logo.webp']);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('promoters.0.logo', 'promoters/logo.webp'));
});
