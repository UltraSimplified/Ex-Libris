<?php

use App\Models\Listing;
use App\Models\User;
use App\Nova\Filters\ListingPeriod;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Staff are identified by the viewNova gate, so these tests use an address
 * from that list rather than a role column, which the users table has not got.
 */
function staffUser(): User
{
    return User::factory()->create(['email' => 'robin@ultrasimplified.com']);
}

test('staff can manage every listing through nova', function () {
    $staff = staffUser();
    $magazineListing = Listing::factory()->create(['user_id' => null]);
    $promoterListing = Listing::factory()->for(User::factory())->create();

    foreach ([$magazineListing, $promoterListing] as $listing) {
        expect(Gate::forUser($staff)->check('view', $listing))->toBeTrue()
            ->and(Gate::forUser($staff)->check('update', $listing))->toBeTrue()
            ->and(Gate::forUser($staff)->check('delete', $listing))->toBeTrue()
            ->and(Gate::forUser($staff)->check('replicate', $listing))->toBeTrue();
    }

    expect(Gate::forUser($staff)->check('create', Listing::class))->toBeTrue();
});

test('a promoter cannot touch listings they do not own', function () {
    $promoter = User::factory()->create(['email' => 'promoter@example.com']);
    $magazineListing = Listing::factory()->create(['user_id' => null]);

    expect(Gate::forUser($promoter)->check('view', $magazineListing))->toBeFalse()
        ->and(Gate::forUser($promoter)->check('update', $magazineListing))->toBeFalse()
        ->and(Gate::forUser($promoter)->check('delete', $magazineListing))->toBeFalse()
        ->and(Gate::forUser($promoter)->check('replicate', $magazineListing))->toBeFalse();
});

test('a promoter keeps full control of their own listings', function () {
    $promoter = User::factory()->agreed()->create(['email' => 'promoter@example.com']);
    $own = Listing::factory()->for($promoter)->create();

    expect(Gate::forUser($promoter)->check('create', Listing::class))->toBeTrue()
        ->and(Gate::forUser($promoter)->check('view', $own))->toBeTrue()
        ->and(Gate::forUser($promoter)->check('update', $own))->toBeTrue()
        ->and(Gate::forUser($promoter)->check('delete', $own))->toBeTrue();
});

/*
 * Somebody who sent a listing in before the office linked them to
 * anything can still read and delete it, but not add, change or copy one:
 * what is saved now goes straight up.
 */
test('an account the office has not agreed can read and delete its listings, but not publish', function () {
    $sender = User::factory()->create();
    $own = Listing::factory()->for($sender)->create();

    expect(Gate::forUser($sender)->check('create', Listing::class))->toBeFalse()
        ->and(Gate::forUser($sender)->check('update', $own))->toBeFalse()
        ->and(Gate::forUser($sender)->check('replicate', $own))->toBeFalse()
        ->and(Gate::forUser($sender)->check('view', $own))->toBeTrue()
        ->and(Gate::forUser($sender)->check('delete', $own))->toBeTrue();
});

test('an ownerless listing is never owned by a promoter', function () {
    $promoter = User::factory()->create(['email' => 'promoter@example.com']);
    $orphan = Listing::factory()->create(['user_id' => null]);

    expect(Gate::forUser($promoter)->check('update', $orphan))->toBeFalse();
});

test('the index filter splits listings on their last day', function () {
    // Finished yesterday, still running, and yet to start.
    $past = Listing::factory()->create(['start_date' => now()->subDays(10), 'end_date' => now()->subDay()]);
    $running = Listing::factory()->create(['start_date' => now()->subDays(10), 'end_date' => now()->addDays(5)]);
    $future = Listing::factory()->create(['start_date' => now()->addMonth(), 'end_date' => null]);
    $today = Listing::factory()->create(['start_date' => now(), 'end_date' => null]);

    $filter = new ListingPeriod;
    $request = NovaRequest::create('/');

    $ids = fn (string $value) => $filter->apply($request, Listing::query(), $value)->pluck('id')->all();

    expect($ids('upcoming'))->toEqualCanonicalizing([$running->id, $future->id, $today->id])
        ->and($ids('past'))->toEqualCanonicalizing([$past->id])
        ->and($ids('all'))->toHaveCount(4);
});

test('the filter defaults to current and upcoming', function () {
    expect((new ListingPeriod)->default())->toBe('upcoming');
});

test('a replicated listing drops the original slug so it regenerates', function () {
    $listing = Listing::factory()->create(['title' => 'Los Fastidios']);

    expect($listing->slug)->toBe('los-fastidios');

    $copy = (new App\Nova\Listing($listing))->replicate()->model();

    expect($copy->slug)->toBeNull();

    $copy->title = 'Los Fastidios: Second Night';
    $copy->save();

    expect($copy->fresh()->slug)->toBe('los-fastidios-second-night')
        ->and($copy->id)->not->toBe($listing->id);
});
