<?php

use App\Models\Listing;
use App\Models\User;
use App\Services\Bookmarks\GuestBookmarks;

test('the panel is given the account\'s saved events, the soonest to finish first', function () {
    $promoter = User::factory()->create(['is_promoter' => true]);
    $later = Listing::factory()->create(['start_date' => today()->addWeek()]);
    $sooner = Listing::factory()->create(['start_date' => today()->addDay()]);
    $promoter->bookmarks()->attach([$later->id, $sooner->id]);

    $this->actingAs($promoter)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('bookmarkedListings', 2)
            ->where('bookmarkedListings.0.id', $sooner->id)
            ->where('bookmarkedListings.1.id', $later->id)
        );
});

test('the panel is given what it draws under each listing', function () {
    $promoter = User::factory()->create(['is_promoter' => true]);
    $listing = Listing::factory()->create(['event_link' => 'https://tickets.example.com/gig', 'ticket_price' => 'Free']);
    $promoter->bookmarks()->attach($listing, ['attending' => true]);

    $this->actingAs($promoter)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('bookmarkedListings.0.ticket_link', fn (string $link) => str_starts_with($link, 'https://tickets.example.com/gig?')
                && str_contains($link, 'utm_campaign=listing-'.$listing->id))
            ->where('bookmarkedListings.0.is_free', true)
            ->where('bookmarkedListings.0.pivot.attending', fn (mixed $attending) => (bool) $attending)
        );
});

test('an event still running counts as still to come', function (array $dates) {
    $promoter = User::factory()->create(['is_promoter' => true]);
    $promoter->bookmarks()->attach(Listing::factory()->create($dates));

    $this->actingAs($promoter)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('bookmarkedListings', 1));
})->with([
    'starting today' => fn () => ['start_date' => today(), 'end_date' => null],
    'ending today' => fn () => ['start_date' => today()->subWeek(), 'end_date' => today()],
    'under way' => fn () => ['start_date' => today()->subWeek(), 'end_date' => today()->addWeek()],
]);

test('an event that has finished is left out', function (array $dates) {
    $promoter = User::factory()->create(['is_promoter' => true]);
    $promoter->bookmarks()->attach(Listing::factory()->create($dates));

    $this->actingAs($promoter)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('bookmarkedListings', 0));
})->with([
    'yesterday' => fn () => ['start_date' => today()->subDay(), 'end_date' => null],
    'a run that ended yesterday' => fn () => ['start_date' => today()->subWeek(), 'end_date' => today()->subDay()],
]);

test('somebody else\'s saved events are not shown', function () {
    $promoter = User::factory()->create(['is_promoter' => true]);
    User::factory()->create()->bookmarks()->attach(Listing::factory()->create());

    $this->actingAs($promoter)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('bookmarkedListings', 0));
});

test('a reader with an event still to come gets the Dashboard', function () {
    $reader = User::factory()->create();
    $listing = Listing::factory()->create(['start_date' => today()->addDay()]);
    $reader->bookmarks()->attach($listing);

    $this->actingAs($reader)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('bookmarkedListings.0.id', $listing->id)
            ->where('venues', [])
            ->where('promoters', [])
        );
});

test('a reader whose saved events have all finished is sent to the home page', function () {
    $reader = User::factory()->create();
    $reader->bookmarks()->attach(Listing::factory()->create(['start_date' => today()->subDay()]));

    $this->actingAs($reader)
        ->get(route('dashboard'))
        ->assertRedirect(route('home'));
});

test('a reader with an event still to come lands on the Dashboard on logging in', function () {
    $reader = User::factory()->create();
    $reader->bookmarks()->attach(Listing::factory()->create(['start_date' => today()->addDay()]));

    $this->followingRedirects()
        ->post(route('login.store'), ['email' => $reader->email, 'password' => 'password'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('an event saved before logging in counts straight away', function () {
    $reader = User::factory()->create();
    $listing = Listing::factory()->create(['start_date' => today()->addDay()]);

    $this->withUnencryptedCookie(GuestBookmarks::COOKIE, (string) $listing->id)
        ->followingRedirects()
        ->post(route('login.store'), ['email' => $reader->email, 'password' => 'password'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('bookmarkedListings.0.id', $listing->id)
        );
});

test('the site\'s links are told who the Dashboard has anything for', function (array $role, ?int $startsInDays, bool $expected) {
    $user = User::factory()->create($role);

    if ($startsInDays !== null) {
        $user->bookmarks()->attach(Listing::factory()->create(['start_date' => today()->addDays($startsInDays)]));
    }

    $this->actingAs($user)
        ->get('/listings')
        ->assertInertia(fn ($page) => $page->where('auth.canOpenDashboard', $expected));
})->with([
    'an account with a role' => [['is_writer' => true], null, true],
    'a reader with an event to come' => [[], 1, true],
    'a reader with a finished event' => [[], -1, false],
    'a reader with nothing saved' => [[], null, false],
]);

/*
 * Saving an event opens the Dashboard, but not the account: who staff may
 * switch to is still decided by what it runs.
 */
test('saving an event does not let staff switch to a reader', function () {
    $reader = User::factory()->create();
    $reader->bookmarks()->attach(Listing::factory()->create(['start_date' => today()->addDay()]));

    expect($reader->canOpenDashboard())->toBeTrue()
        ->and($reader->hasDashboard())->toBeFalse()
        ->and($reader->canBeImpersonated())->toBeFalse();
});

test('a listing is free when its price says so with no figure beside it', function (?string $price, bool $free) {
    expect(new Listing(['ticket_price' => $price])->is_free)->toBe($free);
})->with([
    ['Free', true],
    ['free!', true],
    ['Free entry', true],
    ['Admission free, donations welcome', true],
    ['£10 (adult), £6 (concession), children go free', false],
    ['£18.00 - free for carers and 18 and under', false],
    ['Pay What You Feel', false],
    ['Freedom Festival', false],
    ['', false],
    [null, false],
]);
