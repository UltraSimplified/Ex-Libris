<?php

use App\Models\User;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * What the office has granted an account, held apart from what the
 * account already owns: somebody can be a writer before their first
 * article is published, or be trusted to run venues before one is
 * attached to them.
 */
test('an account holds only the roles it has been granted', function () {
    $user = User::factory()->create(['is_writer' => true]);

    expect($user->hasRole('is_writer'))->toBeTrue()
        ->and($user->hasRole('is_venue_mgr'))->toBeFalse()
        ->and($user->hasRole('is_promoter'))->toBeFalse();
});

test('a new account holds none of them', function () {
    $user = User::factory()->create();

    expect($user->roles())->toBe([
        'is_venue_mgr' => false,
        'is_promoter' => false,
        'is_writer' => false,
        'is_publisher' => false,
    ]);
});

/**
 * Staff are recognised by the viewNova gate, which is an allowlist of
 * addresses - hence the real one here. Somebody who can reach the Office
 * can already do anything a role permits, so ticking three boxes on every
 * staff account would only be something else to keep in step.
 */
test('being staff grants all of them without being granted any', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    expect($staff->roles())->toBe([
        'is_venue_mgr' => true,
        'is_promoter' => true,
        'is_writer' => true,
        'is_publisher' => true,
    ]);

    // The columns themselves are untouched: the permission is inferred.
    expect($staff->fresh()->is_writer)->toBeFalse();
});

test('a role nobody has heard of is not a role', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    expect($staff->hasRole('editor'))->toBeFalse();
});

/**
 * A permission is given in the Office, never submitted from a form, so it
 * stays out of mass assignment.
 */
test('a role cannot be granted by mass assignment', function () {
    $user = User::factory()->create();

    $user->fill(['is_writer' => true, 'is_venue_mgr' => true]);

    expect($user->hasRole('is_writer'))->toBeFalse()
        ->and($user->hasRole('is_venue_mgr'))->toBeFalse();
});

test('the dashboard is told what the account may be', function () {
    $user = User::factory()->create(['is_promoter' => true]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.roles.is_promoter', true)
            ->where('auth.roles.is_writer', false)
            ->where('auth.roles.is_venue_mgr', false)
        );
});

test('a guest is told they may be nothing rather than nothing at all', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.roles', [
            'is_venue_mgr' => false,
            'is_promoter' => false,
            'is_writer' => false,
            'is_publisher' => false,
        ]));
});

/**
 * Articles is the writers' section, so it is held to them - the sidebar
 * offers it to nobody else, and neither does the route.
 */
test('a writer reaches their articles', function () {
    $writer = User::factory()->create(['is_writer' => true]);

    $this->actingAs($writer)
        ->get(route('dashboard.articles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard/articles/Index'));
});

test('staff reach articles like every other section', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    $this->actingAs($staff)
        ->get(route('dashboard.articles.index'))
        ->assertOk();
});

test('everybody else is turned away from articles', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.articles.index'))
        ->assertForbidden();
});

test('a guest is sent to sign in rather than turned away', function () {
    $this->get(route('dashboard.articles.index'))
        ->assertRedirect(route('login'));
});

/**
 * A permission is granted in the Office and nowhere else, so the Office
 * has to offer it. Nova sets a field on the model directly rather than
 * through mass assignment, which is what lets it write a column the
 * dashboard's own forms cannot touch.
 */
test('the office can grant a role', function () {
    $fields = collect((new App\Nova\User(new User))->fields(app(NovaRequest::class)))
        ->map(fn ($field) => $field->attribute);

    expect($fields)->toContain('is_venue_mgr', 'is_promoter', 'is_writer');
});

test('a role set directly is kept', function () {
    $user = User::factory()->create();

    $user->is_writer = true;
    $user->save();

    expect($user->fresh()->hasRole('is_writer'))->toBeTrue();
});
