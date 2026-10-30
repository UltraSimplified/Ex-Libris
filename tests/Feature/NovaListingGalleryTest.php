<?php

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('do');
});

/**
 * Reopening a listing in the Office showed each gallery image as its
 * address rather than the image, as the feature image layouts once did:
 * Flexible copies a layout's fields without what draws an image's
 * thumbnail. The gallery's images are drawn again.
 */
test('a saved gallery image is sent to the listing editor with its thumbnail and preview', function () {
    $path = 'gallery/c83a84c67af49482.webp';
    Storage::disk('do')->put($path, 'image');

    $listing = Listing::factory()->create([
        'gallery' => json_encode([
            ['layout' => 'image', 'key' => 'c83a84c67af49482', 'attributes' => ['image' => $path, 'caption' => null]],
        ]),
    ]);

    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    // Asked for as the editor asks, a plain GET for JSON: as a JSON request
    // Flexible's field rules cannot read it.
    $fields = $this->actingAs($staff)
        ->get('/nova-api/listings/'.$listing->id.'/update-fields?editing=true&editMode=update', [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->json('fields');

    $gallery = collect($fields)->firstWhere('attribute', 'gallery');
    $image = collect($gallery['value'][0]['attributes'])
        ->first(fn (array $field): bool => str_ends_with($field['attribute'], 'image'));

    expect($image['thumbnailUrl'])->toBe(Storage::disk('do')->url($path))
        ->and($image['previewUrl'])->toBe(Storage::disk('do')->url($path))
        ->and($image['deletable'])->toBeFalse();
});
