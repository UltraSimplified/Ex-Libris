<?php

use App\Models\Feature;
use App\Models\User;
use App\Nova\Feature as NovaFeature;
use App\Services\Images\FeatureGallery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Nova\Http\Requests\NovaRequest;
use Ultrasimplified\Gallery\Gallery;
use Whitecube\NovaFlexibleContent\Flexible;
use Whitecube\NovaFlexibleContent\Http\FlexibleAttribute;
use Whitecube\NovaFlexibleContent\Http\Middleware\InterceptFlexibleAttributes;

beforeEach(function () {
    Storage::fake(FeatureGallery::DISK);
});

/**
 * A stored photo and its thumbnail on the fake disk, as the upload route
 * leaves them.
 *
 * @return array{image: string, thumb: string, width: int, height: int, alt: ?string, caption: ?string}
 */
function storedPhotojournalImage(string $key, ?string $caption = null): array
{
    $image = FeatureGallery::DIRECTORY.'/'.$key.'.webp';
    $thumb = FeatureGallery::DIRECTORY.'/'.$key.'-thumb.webp';

    Storage::disk(FeatureGallery::DISK)->put($image, 'photo');
    Storage::disk(FeatureGallery::DISK)->put($thumb, 'thumbnail');

    return ['image' => $image, 'thumb' => $thumb, 'width' => 2000, 'height' => 1333, 'alt' => null, 'caption' => $caption];
}

/**
 * A feature body with a Photojournal between two paragraphs, as Nova stores it.
 *
 * @param  list<array<string, mixed>>  $images
 */
function featureBodyWithPhotojournal(array $images, string $key = 'cGallery0000001'): string
{
    return json_encode([
        ['layout' => 'copy', 'key' => 'cCopy00000000001', 'attributes' => ['text' => '<p>Before.</p>']],
        ['layout' => 'gallery', 'key' => $key, 'attributes' => ['images' => $images]],
        ['layout' => 'copy', 'key' => 'cCopy00000000002', 'attributes' => ['text' => '<p>After.</p>']],
    ]);
}

function novaFeatureBodyField(): Flexible
{
    return collect((new NovaFeature(new Feature))->fields(app(NovaRequest::class)))
        ->first(fn ($field): bool => $field instanceof Flexible);
}

function novaPhotojournalField(): Gallery
{
    return novaFeatureBodyField()->meta()['layouts']->find('gallery')->fields()[0];
}

/**
 * Save a body through Nova's Flexible field, as the editor sends it: the
 * body as JSON, each layout's attributes named with the layout's key in
 * front, unpacked by the Flexible middleware before the field fills.
 *
 * @param  list<array{layout: string, key: string, attributes: array<string, mixed>}>  $layouts
 */
function saveFeatureBodyThroughNova(Feature $feature, array $layouts): void
{
    $body = collect($layouts)->map(fn (array $layout): array => [
        'layout' => $layout['layout'],
        'key' => $layout['key'],
        'attributes' => collect($layout['attributes'])
            ->mapWithKeys(fn ($value, string $name): array => [$layout['key'].FlexibleAttribute::GROUP_SEPARATOR.$name => $value])
            ->all(),
    ])->all();

    $request = NovaRequest::create('/nova-api/features/'.$feature->id, 'PUT', [
        'body' => json_encode($body),
        FlexibleAttribute::REGISTER => json_encode(['body']),
    ]);

    (new InterceptFlexibleAttributes)->handle($request, function (NovaRequest $request) use ($feature) {
        novaFeatureBodyField()->fill($request, $feature);

        return response()->noContent();
    });

    $feature->save();
}

function photojournalStaff(): User
{
    return User::factory()->create(['email' => 'robin@ultrasimplified.com']);
}

test('a photo uploads as a webp scaled to the gallery size, with a thumbnail', function () {
    $response = $this->actingAs(photojournalStaff())->postJson('/nova-vendor/gallery/upload', [
        'image' => UploadedFile::fake()->image('long-table.jpg', 3000, 2000),
        'token' => novaPhotojournalField()->jsonSerialize()['uploadToken'],
    ]);

    $response->assertSuccessful()
        ->assertJson(['width' => 2000, 'height' => 1333, 'alt' => null, 'caption' => null]);

    $stored = $response->json();
    $disk = Storage::disk(FeatureGallery::DISK);

    expect($stored['image'])->toStartWith(FeatureGallery::DIRECTORY.'/')->toEndWith('.webp')
        ->and($stored['thumb'])->toBe(str_replace('.webp', '-thumb.webp', $stored['image']));

    $disk->assertExists([$stored['image'], $stored['thumb']]);

    expect(getimagesizefromstring($disk->get($stored['image']))['mime'])->toBe('image/webp')
        ->and(getimagesizefromstring($disk->get($stored['thumb']))[0])->toBe(FeatureGallery::THUMBNAIL_EDGE);
});

test('a photo smaller than the gallery size is not enlarged', function () {
    $this->actingAs(photojournalStaff())->postJson('/nova-vendor/gallery/upload', [
        'image' => UploadedFile::fake()->image('small.png', 800, 600),
        'token' => novaPhotojournalField()->jsonSerialize()['uploadToken'],
    ])->assertSuccessful()->assertJson(['width' => 800, 'height' => 600]);
});

test('an upload with a token that was not made by the field is refused', function () {
    $this->actingAs(photojournalStaff())->postJson('/nova-vendor/gallery/upload', [
        'image' => UploadedFile::fake()->image('photo.jpg', 100, 100),
        'token' => Crypt::encrypt(['disk' => 'local', 'directory' => 'anywhere']),
    ])->assertForbidden();

    Storage::disk(FeatureGallery::DISK)->assertDirectoryEmpty(FeatureGallery::DIRECTORY);
});

test('an upload that is not a photo is refused', function (UploadedFile $file) {
    $this->actingAs(photojournalStaff())->postJson('/nova-vendor/gallery/upload', [
        'image' => $file,
        'token' => novaPhotojournalField()->jsonSerialize()['uploadToken'],
    ])->assertUnprocessable()->assertJsonValidationErrors('image');
})->with([
    'a pdf' => fn () => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    'an svg' => fn () => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
]);

test('only somebody who can use Nova can upload', function () {
    $upload = fn () => [
        'image' => UploadedFile::fake()->image('photo.jpg', 100, 100),
        'token' => novaPhotojournalField()->jsonSerialize()['uploadToken'],
    ];

    $this->postJson('/nova-vendor/gallery/upload', $upload())->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson('/nova-vendor/gallery/upload', $upload())
        ->assertForbidden();
});

test('the field keeps only photos in the gallery directory, and only what a photo needs', function () {
    $kept = storedPhotojournalImage('cKept0000000001', 'Steve Davis at the decks');

    $images = Gallery::images(json_encode([
        [...$kept, 'url' => 'https://elsewhere.example/x.jpg', 'alt' => '  At The Long Table  '],
        ['image' => 'covers/cover.webp', 'thumb' => 'covers/cover.webp'],
        ['image' => FeatureGallery::DIRECTORY.'/../covers/cover.webp', 'thumb' => FeatureGallery::DIRECTORY.'/x-thumb.webp'],
        'not a photo',
    ]), FeatureGallery::DIRECTORY);

    expect($images)->toBe([[...$kept, 'alt' => 'At The Long Table']]);
});

test('a photojournal saved through nova keeps its photos in order with their captions', function () {
    $first = storedPhotojournalImage('cFirst000000001');
    $second = storedPhotojournalImage('cSecond00000001');
    $feature = Feature::factory()->create(['body' => featureBodyWithPhotojournal([])]);

    saveFeatureBodyThroughNova($feature, [
        ['layout' => 'gallery', 'key' => 'cGallery0000001', 'attributes' => [
            'images' => json_encode([[...$second, 'caption' => 'Gaz Williams'], $first]),
        ]],
    ]);

    $layouts = json_decode($feature->fresh()->body, true);

    expect($layouts)->toHaveCount(1)
        ->and($layouts[0]['layout'])->toBe('gallery')
        ->and($layouts[0]['attributes']['images'])->toBe([[...$second, 'caption' => 'Gaz Williams'], $first]);
});

/**
 * Two Photojournals of twenty photos each, saved together: each keeps its
 * own photos, and none of either is deleted.
 */
test('two photojournals saved through nova keep twenty photos each', function () {
    $first = collect(range(1, 20))->map(fn (int $n): array => storedPhotojournalImage(sprintf('cFirst%09d', $n)))->all();
    $second = collect(range(1, 20))->map(fn (int $n): array => storedPhotojournalImage(sprintf('cSecnd%09d', $n)))->all();
    $feature = Feature::factory()->create(['body' => featureBodyWithPhotojournal([])]);

    saveFeatureBodyThroughNova($feature, [
        ['layout' => 'gallery', 'key' => 'cGallery0000001', 'attributes' => ['images' => json_encode($first)]],
        ['layout' => 'gallery', 'key' => 'cGallery0000002', 'attributes' => ['images' => json_encode($second)]],
    ]);

    $layouts = json_decode($feature->fresh()->body, true);

    expect($layouts)->toHaveCount(2)
        ->and($layouts[0]['attributes']['images'])->toBe($first)
        ->and($layouts[1]['attributes']['images'])->toBe($second);

    Storage::disk(FeatureGallery::DISK)->assertExists(collect([...$first, ...$second])->flatMap(fn (array $image): array => [$image['image'], $image['thumb']])->all());
});

test('a photojournal holds up to twenty photos', function (int $count, bool $passes) {
    $images = collect(range(1, $count))->map(fn (int $n): array => storedPhotojournalImage(sprintf('cPhoto%09d', $n)))->all();
    $field = novaPhotojournalField();

    $validator = Validator::make(
        [$field->attribute => json_encode($images)],
        $field->getRules(app(NovaRequest::class)),
    );

    expect($validator->passes())->toBe($passes);
})->with([
    'twenty' => [20, true],
    'twenty-one' => [21, false],
]);

test('removing a photojournal through nova deletes its photos from the disk', function () {
    $images = [storedPhotojournalImage('cOne00000000001'), storedPhotojournalImage('cTwo00000000001')];
    $feature = Feature::factory()->create(['body' => featureBodyWithPhotojournal($images)]);

    saveFeatureBodyThroughNova($feature, [
        ['layout' => 'copy', 'key' => 'cCopy00000000001', 'attributes' => ['text' => '<p>Before.</p>']],
    ]);

    Storage::disk(FeatureGallery::DISK)->assertMissing([
        $images[0]['image'], $images[0]['thumb'], $images[1]['image'], $images[1]['thumb'],
    ]);
});

test('removing one photo deletes only that photo', function () {
    $kept = storedPhotojournalImage('cKept0000000001');
    $dropped = storedPhotojournalImage('cDropped0000001');
    $feature = Feature::factory()->create(['body' => featureBodyWithPhotojournal([$kept, $dropped])]);

    $feature->forceFill(['body' => featureBodyWithPhotojournal([$kept])])->save();

    Storage::disk(FeatureGallery::DISK)->assertExists([$kept['image'], $kept['thumb']]);
    Storage::disk(FeatureGallery::DISK)->assertMissing([$dropped['image'], $dropped['thumb']]);
});

test('deleting a feature deletes the photos in its photojournal', function () {
    $images = [storedPhotojournalImage('cOne00000000001'), storedPhotojournalImage('cTwo00000000001')];
    $feature = Feature::factory()->create(['body' => featureBodyWithPhotojournal($images)]);

    $feature->delete();

    Storage::disk(FeatureGallery::DISK)->assertMissing([
        $images[0]['image'], $images[0]['thumb'], $images[1]['image'], $images[1]['thumb'],
    ]);
});

test('a photo another feature still shows is kept', function () {
    $shared = storedPhotojournalImage('cShared00000001');
    $feature = Feature::factory()->create(['body' => featureBodyWithPhotojournal([$shared])]);
    Feature::factory()->create(['body' => featureBodyWithPhotojournal([$shared], 'cGallery0000002')]);

    $feature->delete();

    Storage::disk(FeatureGallery::DISK)->assertExists([$shared['image'], $shared['thumb']]);
});

test('files outside the gallery directory are never deleted', function () {
    Storage::disk(FeatureGallery::DISK)->put('covers/cover.webp', 'cover');
    $feature = Feature::factory()->create(['body' => featureBodyWithPhotojournal([
        ['image' => 'covers/cover.webp', 'thumb' => 'covers/cover.webp'],
    ])]);

    $feature->delete();

    Storage::disk(FeatureGallery::DISK)->assertExists('covers/cover.webp');
});

test('saving a feature without touching its photojournal deletes nothing', function () {
    $images = [storedPhotojournalImage('cOne00000000001')];
    $feature = Feature::factory()->create(['body' => featureBodyWithPhotojournal($images)]);

    $feature->forceFill(['title' => 'A new title'])->save();
    $feature->forceFill(['body' => featureBodyWithPhotojournal($images)])->save();

    Storage::disk(FeatureGallery::DISK)->assertExists([$images[0]['image'], $images[0]['thumb']]);
});
