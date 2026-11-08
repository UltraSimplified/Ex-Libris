<?php

use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Most of what somebody lists is a repeat of something they have listed
 * before - the same room, the same night, a month later - so a listing
 * can be copied and the copy opened for editing.
 */
beforeEach(function () {
    Storage::fake('do');
});

test('a listing can be duplicated by whoever may edit it', function () {
    $owner = User::factory()->has(Promoter::factory())->create();
    $listing = Listing::factory()->create([
        'user_id' => $owner->id,
        'title' => 'Quiz Night',
        'sold_out' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('dashboard.listings.duplicate', $listing))
        ->assertRedirect();

    $copy = Listing::query()->where('title', 'Copy of Quiz Night')->first();

    expect($copy)->not->toBeNull()
        ->and($copy->id)->not->toBe($listing->id)
        ->and($copy->slug)->toBe('copy-of-quiz-night')
        ->and($copy->sold_out)->toBeTrue()
        ->and($copy->user_id)->toBe($owner->id);
});

test('the copy is opened for editing rather than left in the list', function () {
    $owner = User::factory()->has(Promoter::factory())->create();
    $listing = Listing::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('dashboard.listings.duplicate', $listing))
        ->assertRedirect(
            route('dashboard.listings.edit', Listing::query()->latest('id')->first())
        );
});

test('somebody else cannot duplicate a listing', function () {
    $listing = Listing::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs(User::factory()->create())
        ->post(route('dashboard.listings.duplicate', $listing))
        ->assertForbidden();

    expect(Listing::query()->count())->toBe(1);
});

test('a guest is sent to sign in rather than turned away', function () {
    $listing = Listing::factory()->create();

    $this->post(route('dashboard.listings.duplicate', $listing))
        ->assertRedirect(route('login'));
});

test('only an agreed account may duplicate a listing', function () {
    $sender = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $sender->id]);

    $this->actingAs($sender)
        ->post(route('dashboard.listings.duplicate', $listing))
        ->assertForbidden();

    expect(Listing::count())->toBe(1);
});

/**
 * Deleting a listing deletes the files behind it, so a copy that shared
 * the original's paths would strip the picture off whichever was left.
 */
test('the copy gets its own image files', function () {
    $owner = User::factory()->has(Promoter::factory())->create();

    Storage::disk('do')->put('original.webp', 'a picture');

    $listing = Listing::factory()->create([
        'user_id' => $owner->id,
        'thumbnail' => 'original.webp',
    ]);

    $this->actingAs($owner)
        ->post(route('dashboard.listings.duplicate', $listing));

    $copy = Listing::query()->latest('id')->first();

    expect($copy->thumbnail)->not->toBeNull()
        ->and($copy->thumbnail)->not->toBe('original.webp')
        ->and(Storage::disk('do')->exists($copy->thumbnail))->toBeTrue();

    // Deleting one leaves the other with its picture.
    $this->actingAs($owner)->delete(route('dashboard.listings.destroy', $listing));

    expect(Storage::disk('do')->exists($copy->thumbnail))->toBeTrue();
});

test('a listing with no picture copies cleanly', function () {
    $owner = User::factory()->has(Promoter::factory())->create();
    $listing = Listing::factory()->create(['user_id' => $owner->id, 'thumbnail' => null]);

    $this->actingAs($owner)
        ->post(route('dashboard.listings.duplicate', $listing))
        ->assertRedirect();

    expect(Listing::query()->latest('id')->first()->thumbnail)->toBeNull();
});

test('a duplicated listing keeps its specific days', function () {
    $owner = User::factory()->has(Promoter::factory())->create();
    $quiz = Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create([
        'user_id' => $owner->id,
        'title' => 'Pub Quiz',
    ]);

    $this->actingAs($owner)
        ->post(route('dashboard.listings.duplicate', $quiz))
        ->assertRedirect();

    $copy = Listing::query()->where('title', 'Copy of Pub Quiz')->sole();

    expect($copy->start_date->toDateString())->toBe('2026-11-03')
        ->and($copy->end_date->toDateString())->toBe('2026-12-15')
        ->and($copy->days->map(fn ($day) => $day->day->toDateString())->all())
        ->toBe(['2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15']);
});
