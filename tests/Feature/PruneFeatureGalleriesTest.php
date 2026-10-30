<?php

use App\Models\Feature;
use App\Services\Images\FeatureGallery;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(FeatureGallery::DISK);
});

/**
 * A gallery file on the fake disk, last changed the given number of hours ago.
 */
function galleryFileAged(string $name, int $hoursOld): string
{
    $path = FeatureGallery::DIRECTORY.'/'.$name;
    $disk = Storage::disk(FeatureGallery::DISK);

    $disk->put($path, 'photo');
    touch($disk->path($path), now()->subHours($hoursOld)->getTimestamp());

    return $path;
}

function featureShowingGalleryFiles(string $image, string $thumb): Feature
{
    return Feature::factory()->create(['body' => json_encode([
        ['layout' => 'gallery', 'key' => 'cGallery0000001', 'attributes' => ['images' => [
            ['image' => $image, 'thumb' => $thumb, 'width' => 2000, 'height' => 1333, 'alt' => null, 'caption' => null],
        ]]],
    ])]);
}

test('photos no feature shows are cleared down once they are a day old', function () {
    $orphan = galleryFileAged('cOrphan00000001.webp', 30);
    $orphanThumb = galleryFileAged('cOrphan00000001-thumb.webp', 30);

    $this->artisan('galleries:prune', ['--commit' => true])
        ->expectsOutputToContain('Cleared down 2 file(s).')
        ->assertSuccessful();

    Storage::disk(FeatureGallery::DISK)->assertMissing([$orphan, $orphanThumb]);
});

test('photos a feature shows are kept, however old', function () {
    $image = galleryFileAged('cShown000000001.webp', 24 * 400);
    $thumb = galleryFileAged('cShown000000001-thumb.webp', 24 * 400);
    featureShowingGalleryFiles($image, $thumb);

    $this->artisan('galleries:prune', ['--commit' => true])
        ->expectsOutputToContain('Nothing to clear down.')
        ->assertSuccessful();

    Storage::disk(FeatureGallery::DISK)->assertExists([$image, $thumb]);
});

test('a photo uploaded today is left for whoever is still editing', function () {
    $recent = galleryFileAged('cRecent00000001.webp', 2);

    $this->artisan('galleries:prune', ['--commit' => true])->assertSuccessful();

    Storage::disk(FeatureGallery::DISK)->assertExists($recent);
});

test('without --commit it only says what it would clear down', function () {
    $orphan = galleryFileAged('cOrphan00000001.webp', 30);

    $this->artisan('galleries:prune')
        ->expectsOutputToContain('1 file(s) would be cleared down')
        ->assertSuccessful();

    Storage::disk(FeatureGallery::DISK)->assertExists($orphan);
});

test('files outside the gallery directory are left alone', function () {
    $disk = Storage::disk(FeatureGallery::DISK);
    $disk->put('covers/cover.webp', 'cover');
    touch($disk->path('covers/cover.webp'), now()->subDays(30)->getTimestamp());

    $this->artisan('galleries:prune', ['--commit' => true])->assertSuccessful();

    $disk->assertExists('covers/cover.webp');
});
