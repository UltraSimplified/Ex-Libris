<?php

use App\Models\Feature;
use App\Services\Images\FeatureGallery;

/**
 * A photo as the Nova Gallery field stores it. The files themselves are not
 * needed: the page is only asked which photo it is showing.
 *
 * @return array<string, mixed>
 */
function photojournalPhoto(string $key, ?string $caption = null, int $width = 2000, int $height = 1333): array
{
    return [
        'image' => FeatureGallery::DIRECTORY.'/'.$key.'.webp',
        'thumb' => FeatureGallery::DIRECTORY.'/'.$key.'-thumb.webp',
        'width' => $width,
        'height' => $height,
        'alt' => 'Photo '.$key,
        'caption' => $caption,
    ];
}

/**
 * A feature body with a Photojournal between two paragraphs, as Nova stores it.
 *
 * @param  list<array<string, mixed>>|null  $images
 */
function featureBodyAroundPhotojournal(?array $images): string
{
    return json_encode([
        ['layout' => 'copy', 'key' => 'one', 'attributes' => ['text' => '<p>Before the photojournal.</p>']],
        ['layout' => 'gallery', 'key' => 'two', 'attributes' => ['images' => $images]],
        ['layout' => 'copy', 'key' => 'three', 'attributes' => ['text' => '<p>After the photojournal.</p>']],
    ]);
}

/*
 * While a photo slides or fades out, the frame holds it and the photo
 * replacing it, which is always the frame's last layer.
 */
const SHOWN_PHOTO = '.gallery-frame > :last-child img';
const PHOTOJOURNAL = 'section[aria-label="Photojournal"]';

test('a photojournal shows its first photo, with a thumbnail for every photo, between the text around it', function () {
    $feature = Feature::factory()->create(['body' => featureBodyAroundPhotojournal([
        photojournalPhoto('cOne00000000001', 'Steve Davis at the decks'),
        photojournalPhoto('cTwo00000000001', 'Gaz Williams', 1333, 2000),
        photojournalPhoto('cThree000000001'),
    ])]);

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->assertSee('Before the photojournal.')
        ->assertAttributeContains(SHOWN_PHOTO, 'src', 'features/galleries/cOne00000000001.webp')
        ->assertSee('Steve Davis at the decks')
        ->assertSee('1 / 3')
        ->assertCount('section[aria-label="Photojournal"] li button', 3)
        ->assertSee('After the photojournal.')
        ->assertDontSee('OTHER')
        ->assertNoJavaScriptErrors();
});

test('the arrows, the thumbnails and the arrow keys turn the photos, round and round', function () {
    $feature = Feature::factory()->create(['body' => featureBodyAroundPhotojournal([
        photojournalPhoto('cOne00000000001', 'Steve Davis at the decks'),
        photojournalPhoto('cTwo00000000001', 'Gaz Williams', 1333, 2000),
        photojournalPhoto('cThree000000001'),
    ])]);

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->click('[aria-label="Next photo"]')
        ->assertSee('2 / 3')
        ->assertSee('Gaz Williams')
        ->assertAttributeContains(SHOWN_PHOTO, 'src', 'cTwo00000000001.webp')
        ->click('[aria-label="Show photo 3"]')
        ->assertSee('3 / 3')
        ->assertAttributeContains(SHOWN_PHOTO, 'src', 'cThree000000001.webp')
        ->click('[aria-label="Next photo"]')
        ->assertSee('1 / 3')
        ->click('[aria-label="Previous photo"]')
        ->assertSee('3 / 3')
        ->keys('section[aria-label="Photojournal"] figure', 'ArrowRight')
        ->assertSee('1 / 3')
        ->assertNoJavaScriptErrors();
});

test('the arrows and arrow keys slide the photos along, and a thumbnail fades its photo in', function () {
    $feature = Feature::factory()->create(['body' => featureBodyAroundPhotojournal([
        photojournalPhoto('cOne00000000001'),
        photojournalPhoto('cTwo00000000001'),
        photojournalPhoto('cThree000000001'),
    ])]);

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->click('[aria-label="Next photo"]')
        ->assertAttribute(PHOTOJOURNAL, 'data-turn', 'next')
        ->assertPresent('.gallery-frame > .gallery-next-enter-active')
        ->click('[aria-label="Previous photo"]')
        ->assertAttribute(PHOTOJOURNAL, 'data-turn', 'previous')
        ->click('[aria-label="Show photo 3"]')
        ->assertAttribute(PHOTOJOURNAL, 'data-turn', 'fade')
        ->assertPresent('.gallery-frame > .gallery-fade-enter-active')
        ->keys('section[aria-label="Photojournal"] figure', 'ArrowLeft')
        ->assertAttribute(PHOTOJOURNAL, 'data-turn', 'previous')
        ->assertNoJavaScriptErrors();
});

test('once a photo has slid or faded in, it is the only one left in the frame', function () {
    $feature = Feature::factory()->create(['body' => featureBodyAroundPhotojournal([
        photojournalPhoto('cOne00000000001'),
        photojournalPhoto('cTwo00000000001'),
        photojournalPhoto('cThree000000001'),
    ])]);

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->click('[aria-label="Next photo"]')
        ->click('[aria-label="Show photo 3"]')
        ->wait(1)
        ->assertCount('.gallery-frame img', 1)
        ->assertAttributeContains('.gallery-frame img', 'src', 'cThree000000001.webp')
        ->assertNoJavaScriptErrors();
});

test('a photojournal of one photo has no arrows, counter or thumbnails', function () {
    $feature = Feature::factory()->create(['body' => featureBodyAroundPhotojournal([
        photojournalPhoto('cOnly0000000001', 'The only one'),
    ])]);

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->assertAttributeContains(SHOWN_PHOTO, 'src', 'cOnly0000000001.webp')
        ->assertSee('The only one')
        ->assertMissing('[aria-label="Next photo"]')
        ->assertMissing('section[aria-label="Photojournal"] ul')
        ->assertDontSee('1 / 1')
        ->assertNoJavaScriptErrors();
});

test('a photojournal left empty draws nothing', function (?array $images) {
    $feature = Feature::factory()->create(['body' => featureBodyAroundPhotojournal($images)]);

    visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->assertSee('Before the photojournal.')
        ->assertSee('After the photojournal.')
        ->assertMissing('section[aria-label="Photojournal"]')
        ->assertDontSee('OTHER')
        ->assertNoJavaScriptErrors();
})->with([
    'no photos' => [[]],
    'nothing at all' => [null],
]);
