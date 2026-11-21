<?php

use App\Support\SharedBucketDisk;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

/**
 * Every site shares the live site's Spaces bucket, so only the live site
 * may take anything out of it.
 */
test('only the live site is given Spaces disks that delete', function (string $environment, bool $keepsFiles) {
    app()['env'] = $environment;

    foreach (['do', 'image', 'file', 's3'] as $disk) {
        Storage::forgetDisk($disk);

        expect(Storage::disk($disk) instanceof SharedBucketDisk)->toBe($keepsFiles, "the {$disk} disk on {$environment}");
    }
})->with([
    'the live site' => ['production', false],
    'staging' => ['development', true],
    'a local checkout' => ['local', true],
]);

/*
 * Built over a fake disk, so the files it is asked about are real ones in
 * a temporary directory rather than in the bucket.
 */
function sharedBucketDiskOverFake(): SharedBucketDisk
{
    $fake = Storage::fake('do');

    return new SharedBucketDisk(
        $fake->getDriver(),
        $fake->getAdapter(),
        [],
        new S3Client(['region' => 'test', 'version' => 'latest', 'credentials' => false]),
    );
}

test('away from the live site, files asked to be deleted stay in the bucket', function () {
    $disk = sharedBucketDiskOverFake();
    $disk->put('listing-thumbnail.webp', 'thumbnail');
    $disk->put('gallery/one.webp', 'photo');
    $disk->put('gallery/two.webp', 'photo');
    $disk->put('features/galleries/three.webp', 'photo');

    expect($disk->delete('listing-thumbnail.webp'))->toBeTrue()
        ->and($disk->delete(['gallery/one.webp', 'gallery/two.webp']))->toBeTrue()
        ->and($disk->deleteDirectory('features/galleries'))->toBeTrue();

    Storage::disk('do')->assertExists([
        'listing-thumbnail.webp',
        'gallery/one.webp',
        'gallery/two.webp',
        'features/galleries/three.webp',
    ]);
});

test('away from the live site, a file moved is copied, so it is still there under its old name', function () {
    $disk = sharedBucketDiskOverFake();
    $disk->put('gallery/before.webp', 'photo');

    expect($disk->move('gallery/before.webp', 'gallery/after.webp'))->toBeTrue();

    Storage::disk('do')->assertExists(['gallery/before.webp', 'gallery/after.webp']);
    expect(Storage::disk('do')->get('gallery/after.webp'))->toBe('photo');
});
