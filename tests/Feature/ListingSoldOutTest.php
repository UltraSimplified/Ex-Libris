<?php

use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;

/**
 * An event that has sold out says so across its own image. The saying is
 * done in the page; what matters here is that the tick box reaches the
 * column and the column reaches the page.
 */
test('nothing is sold out until somebody says it is', function () {
    expect(Listing::factory()->create()->sold_out)->toBeFalse();
});

test('the tick box marks a listing sold out', function () {
    $owner = User::factory()->has(Promoter::factory())->create();
    $listing = Listing::factory()->create([
        'user_id' => $owner->id,
        'promoter_id' => $owner->promoters()->first()->id,
        'sold_out' => false,
    ]);

    $this->actingAs($owner)
        ->patch(route('dashboard.listings.update', $listing), [
            ...soldOutPayload($owner),
            'sold_out' => '1',
        ])
        ->assertRedirect();

    expect($listing->fresh()->sold_out)->toBeTrue();
});

/**
 * An unticked checkbox submits nothing at all, so the form sends a hidden
 * zero alongside it. Without that a listing marked sold out could never
 * be put back on sale.
 */
test('unticking it puts the event back on sale', function () {
    $owner = User::factory()->has(Promoter::factory())->create();
    $listing = Listing::factory()->create([
        'user_id' => $owner->id,
        'promoter_id' => $owner->promoters()->first()->id,
        'sold_out' => true,
    ]);

    $this->actingAs($owner)
        ->patch(route('dashboard.listings.update', $listing), [
            ...soldOutPayload($owner),
            'sold_out' => '0',
        ])
        ->assertRedirect();

    expect($listing->fresh()->sold_out)->toBeFalse();
});

test('a listing that says nothing about it is left alone', function () {
    $owner = User::factory()->has(Promoter::factory())->create();
    $listing = Listing::factory()->create([
        'user_id' => $owner->id,
        'promoter_id' => $owner->promoters()->first()->id,
        'sold_out' => true,
    ]);

    // A browser that sent neither field - the checkbox reads as unticked
    // rather than as "leave it as it was".
    $this->actingAs($owner)
        ->patch(route('dashboard.listings.update', $listing), soldOutPayload($owner))
        ->assertRedirect();

    expect($listing->fresh()->sold_out)->toBeFalse();
});

test('the public listing carries whether it has sold out', function () {
    $listing = Listing::factory()->create([
        'sold_out' => true,
        'start_date' => today()->addWeek(),
    ]);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('listings.0.sold_out', true));

    $this->get(route('Listing', ['id' => $listing->id, 'slug' => $listing->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('listing.sold_out', true));
});

/**
 * The shared payload, pointed at a promoter this account actually runs
 * and at no venue: an account that speaks for a promoter cannot put an
 * event in somebody else's room.
 *
 * @return array<string, mixed>
 */
function soldOutPayload(User $owner): array
{
    return listingPayload([
        'venue_id' => null,
        'promoter_id' => $owner->promoters()->first()->id,
    ]);
}
