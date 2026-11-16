<?php

use App\Models\User;
use App\Models\Venue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * The public header puts a link to the Office under the logotype for the
 * office's own accounts, and only for them: it reads where the Office is
 * from what every page is told.
 */
test('the office accounts are told where the office is on the public pages', function (string $email) {
    $this->actingAs(User::factory()->create(['email' => $email]))
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.office', '/office'));
})->with(User::OFFICE_EMAILS);

test('nobody else on the public pages is told where the office is', function () {
    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.office', null));

    $this->actingAs(User::factory()->writer()->create())
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.office', null));
});

/**
 * The header puts a link to the dashboard under the logotype for any
 * account holding a role, reading the roles every page is told.
 */
test('an account with a role is told it has one, for the dashboard link', function () {
    $this->actingAs(User::factory()->writer()->create())
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.roles.is_writer', true));
});

test('an account kept only for bookmarks holds no role, so gets no dashboard link', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.roles', [
            'is_venue_mgr' => false,
            'is_promoter' => false,
            'is_writer' => false,
            'is_publisher' => false,
        ]));
});

test('a guest\'s dashboard is their Account, and nobody signed in has one', function () {
    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.backstage', false));

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.backstage', false));
});

test('an account that works with the magazine has Backstage', function (User $account) {
    $this->actingAs($account)
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.backstage', true));
})->with([
    'a publisher' => fn (): User => User::factory()->publisher()->create(),
    'a promoter' => fn (): User => User::factory()->create(['is_promoter' => true]),
    'a venue manager' => fn (): User => User::factory()->create(['is_venue_mgr' => true]),
    'a writer' => fn (): User => User::factory()->writer()->create(),
    'somebody running a venue' => fn (): User => Venue::factory()->for(User::factory())->create()->user,
    'the office' => fn (): User => User::factory()->create(['email' => 'robin@ultrasimplified.com']),
]);
