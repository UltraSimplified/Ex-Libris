<?php

use App\Models\Feature;
use App\Models\User;
use App\Services\Images\FeatureImages;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(FeatureImages::DISK);
});

/**
 * An image on the fake disk, named the way Nova names an upload.
 */
function storedLayoutImage(string $name): string
{
    $path = $name.'.webp';

    Storage::disk(FeatureImages::DISK)->put($path, 'image');

    return $path;
}

/**
 * A feature body with one of each image layout, as Nova stores it.
 */
function featureBodyWithImageLayouts(?string $full, ?string $left, ?string $right): string
{
    return json_encode(array_values(array_filter([
        $full === null ? null : ['layout' => 'imgfull', 'key' => 'cFull00000000001', 'attributes' => ['image' => $full, 'link' => null, 'alt' => null, 'caption' => null]],
        $left === null ? null : ['layout' => 'imgleft', 'key' => 'cLeft00000000001', 'attributes' => ['image' => $left, 'link' => null, 'alt' => null, 'caption' => null, 'text' => '<p>Left.</p>']],
        $right === null ? null : ['layout' => 'imgright', 'key' => 'cRight0000000001', 'attributes' => ['image' => $right, 'link' => null, 'alt' => null, 'caption' => null, 'text' => '<p>Right.</p>']],
    ])));
}

/**
 * The image field of each image layout in the body, as the Nova editor is
 * sent it when a feature is opened for editing.
 *
 * @return array<string, array<string, mixed>>
 */
function imageFieldsSentToTheEditor(Feature $feature): array
{
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);

    // Asked for as the editor asks, a plain GET for JSON. Made as a JSON
    // request instead, Flexible's field rules cannot read it and it fails.
    $fields = test()->actingAs($staff)
        ->get('/nova-api/features/'.$feature->id.'/update-fields?editing=true&editMode=update', [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->json('fields');

    $body = collect($fields)->firstWhere('attribute', 'body');

    return collect($body['value'])
        ->filter(fn (array $layout): bool => in_array($layout['layout'], FeatureImages::IMAGE_LAYOUTS, true))
        ->mapWithKeys(fn (array $layout): array => [
            $layout['layout'] => collect($layout['attributes'])->first(fn (array $field): bool => str_ends_with($field['attribute'], 'image')),
        ])
        ->all();
}

test('a saved image is sent to the editor with its thumbnail, in every image layout', function () {
    $full = storedLayoutImage('FullWidthImage0000000000000000000000000001');
    $left = storedLayoutImage('LeftImage000000000000000000000000000000001');
    $right = storedLayoutImage('RightImage00000000000000000000000000000001');
    $feature = Feature::factory()->create(['body' => featureBodyWithImageLayouts($full, $left, $right)]);

    $sent = imageFieldsSentToTheEditor($feature);

    expect($sent)->toHaveKeys(['imgfull', 'imgleft', 'imgright']);

    foreach (['imgfull' => $full, 'imgleft' => $left, 'imgright' => $right] as $layout => $path) {
        expect($sent[$layout]['thumbnailUrl'])->toBe(Storage::disk(FeatureImages::DISK)->url($path), $layout.' has no thumbnail')
            ->and($sent[$layout]['previewUrl'])->toBe(Storage::disk(FeatureImages::DISK)->url($path), $layout.' has no preview');
    }
});

test('the editor offers no delete button inside an image layout, since it cannot reach one there', function () {
    $feature = Feature::factory()->create(['body' => featureBodyWithImageLayouts(
        storedLayoutImage('FullWidthImage0000000000000000000000000001'),
        storedLayoutImage('LeftImage000000000000000000000000000000001'),
        storedLayoutImage('RightImage00000000000000000000000000000001'),
    )]);

    foreach (imageFieldsSentToTheEditor($feature) as $layout => $field) {
        expect($field['deletable'])->toBeFalse($layout.' still offers to delete');
    }
});

test('removing an image layout deletes its image from the disk', function (string $removed) {
    $images = [
        'imgfull' => storedLayoutImage('FullWidthImage0000000000000000000000000001'),
        'imgleft' => storedLayoutImage('LeftImage000000000000000000000000000000001'),
        'imgright' => storedLayoutImage('RightImage00000000000000000000000000000001'),
    ];
    $feature = Feature::factory()->create(['body' => featureBodyWithImageLayouts(...array_values($images))]);

    $kept = $images;
    $kept[$removed] = null;
    $feature->forceFill(['body' => featureBodyWithImageLayouts(...array_values($kept))])->save();

    Storage::disk(FeatureImages::DISK)->assertMissing($images[$removed]);
    Storage::disk(FeatureImages::DISK)->assertExists(array_values(array_filter($kept)));
})->with(['imgfull', 'imgleft', 'imgright']);

test('replacing the image in a layout deletes the one it replaced', function () {
    $old = storedLayoutImage('OldImage0000000000000000000000000000000001');
    $new = storedLayoutImage('NewImage0000000000000000000000000000000001');
    $feature = Feature::factory()->create(['body' => featureBodyWithImageLayouts(null, $old, null)]);

    $feature->forceFill(['body' => featureBodyWithImageLayouts(null, $new, null)])->save();

    Storage::disk(FeatureImages::DISK)->assertMissing($old);
    Storage::disk(FeatureImages::DISK)->assertExists($new);
});

test('deleting a feature deletes the images in its image layouts', function () {
    $images = [
        storedLayoutImage('FullWidthImage0000000000000000000000000001'),
        storedLayoutImage('LeftImage000000000000000000000000000000001'),
        storedLayoutImage('RightImage00000000000000000000000000000001'),
    ];
    $feature = Feature::factory()->create(['body' => featureBodyWithImageLayouts(...$images)]);

    $feature->delete();

    Storage::disk(FeatureImages::DISK)->assertMissing($images);
});

test('an image another feature still shows is kept', function () {
    $shared = storedLayoutImage('SharedImage000000000000000000000000000001');
    $feature = Feature::factory()->create(['body' => featureBodyWithImageLayouts($shared, null, null)]);
    Feature::factory()->create(['body' => featureBodyWithImageLayouts(null, null, $shared)]);

    $feature->delete();

    Storage::disk(FeatureImages::DISK)->assertExists($shared);
});

test('an image that is a feature\'s cover photo or feature image is kept', function (string $column) {
    $cover = storedLayoutImage('CoverImage00000000000000000000000000000001');
    $feature = Feature::factory()->create(['body' => featureBodyWithImageLayouts($cover, null, null)]);
    Feature::factory()->create([$column => $cover]);

    $feature->delete();

    Storage::disk(FeatureImages::DISK)->assertExists($cover);
})->with(['cover_photo', 'feature_image']);

test('saving a feature without touching its images deletes nothing', function () {
    $images = [
        storedLayoutImage('FullWidthImage0000000000000000000000000001'),
        storedLayoutImage('LeftImage000000000000000000000000000000001'),
        storedLayoutImage('RightImage00000000000000000000000000000001'),
    ];
    $feature = Feature::factory()->create(['body' => featureBodyWithImageLayouts(...$images)]);

    $feature->forceFill(['title' => 'A new title'])->save();
    $feature->forceFill(['body' => featureBodyWithImageLayouts(...$images)])->save();

    Storage::disk(FeatureImages::DISK)->assertExists($images);
});

test('an image layout holding something other than a path on the disk is left alone', function (mixed $image) {
    Storage::disk(FeatureImages::DISK)->put('covers/cover.webp', 'cover');
    $feature = Feature::factory()->create(['body' => json_encode([
        ['layout' => 'imgfull', 'key' => 'cFull00000000001', 'attributes' => ['image' => $image]],
    ])]);

    $feature->delete();

    Storage::disk(FeatureImages::DISK)->assertExists('covers/cover.webp');
})->with([
    'a web address' => 'https://elsewhere.example/covers/cover.webp',
    'a path climbing out' => 'features/../covers/cover.webp',
    'nothing' => null,
    'a list' => [['covers/cover.webp']],
]);
