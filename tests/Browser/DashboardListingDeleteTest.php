<?php

use App\Models\Listing;
use App\Models\User;

/*
 * Deleting a listing cannot be undone, so the dashboard asks first, in its
 * own dialog naming the listing, rather than the browser's confirm box.
 */
test('delete asks first, and cancelling keeps the listing', function () {
    $owner = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($owner)->create(['title' => 'Fabric Swap at Emergence Commons']);
    $this->actingAs($owner);

    visit('/dashboard/listings')
        ->resize(390, 900)
        ->click('[data-test="delete-listing"]')
        ->assertSee('Delete this listing?')
        ->assertSee('“Fabric Swap at Emergence Commons” will be taken off Good on Paper')
        ->click('Cancel')
        ->assertDontSee('Delete this listing?')
        ->assertSee('Fabric Swap at Emergence Commons')
        ->assertNoJavaScriptErrors();

    expect(Listing::query()->whereKey($listing->id)->exists())->toBeTrue();
});

test('confirming in the dialog deletes the listing', function () {
    $owner = User::factory()->agreed()->create();
    $listing = Listing::factory()->for($owner)->create(['title' => 'Fabric Swap at Emergence Commons']);
    $this->actingAs($owner);

    visit('/dashboard/listings')
        ->click('[data-test="delete-listing"]')
        ->click('[data-test="confirm-delete-listing"]')
        ->assertDontSee('Delete this listing?')
        ->assertDontSee('Fabric Swap at Emergence Commons')
        ->assertNoJavaScriptErrors();

    expect(Listing::query()->whereKey($listing->id)->exists())->toBeFalse();
});
