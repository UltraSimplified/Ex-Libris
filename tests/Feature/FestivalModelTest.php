<?php

use App\Models\Festival;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\Widget;
use App\Support\Brand;
use Illuminate\Support\Facades\Storage;

/**
 * A festival is known by its own colours and pictures, never its
 * promoter's, and it takes its widgets and pictures with it when it goes
 * while its listings stay behind.
 */
test('an unset colour falls back to Good on Paper rather than the promoter', function () {
    $promoter = Promoter::factory()->create([
        'brand_colour' => '#00aa00',
        'accent_colour1' => '#0000ff',
    ]);
    $festival = Festival::factory()->for($promoter)->create(['brand_colour' => '#ff69b4']);

    $brand = $festival->brand();

    expect($brand->brand)->toBe('#ff69b4')
        ->and($brand->accent)->toBe(Brand::ACCENT);
});

test('the mark sits on the festival background colour, or Good on Paper white without one', function () {
    $coloured = Festival::factory()->create(['brand_background_colour' => '#1e90ff']);
    $plain = Festival::factory()->create();

    expect($coloured->mark_background)->toBe('#1e90ff')
        ->and($plain->mark_background)->toBe(Brand::BACKGROUND)
        ->and($plain->toArray())->toHaveKey('mark_background', Brand::BACKGROUND);
});

test('deleting a festival frees its listings and deletes its widgets and pictures', function () {
    Storage::fake('do');
    Storage::disk('do')->put('festival-logo.webp', 'logo');
    Storage::disk('do')->put('festival-banner.webp', 'banner');

    $festival = Festival::factory()->create([
        'logo' => 'festival-logo.webp',
        'banner' => 'festival-banner.webp',
    ]);
    $listing = Listing::factory()->create([
        'promoter_id' => $festival->promoter_id,
        'festival_id' => $festival->id,
    ]);
    $widget = Widget::factory()->create([
        'owner_type' => Festival::class,
        'owner_id' => $festival->id,
    ]);

    $festival->delete();

    expect($listing->fresh())->not->toBeNull()
        ->and($listing->fresh()->festival_id)->toBeNull()
        ->and($listing->fresh()->promoter_id)->toBe($festival->promoter_id)
        ->and(Widget::find($widget->id))->toBeNull();

    Storage::disk('do')->assertMissing('festival-logo.webp');
    Storage::disk('do')->assertMissing('festival-banner.webp');
});

test('deleting a promoter deletes its festivals with their widgets and pictures', function () {
    Storage::fake('do');
    Storage::disk('do')->put('festival-logo.webp', 'logo');

    $promoter = Promoter::factory()->create();
    $festival = Festival::factory()->for($promoter)->create(['logo' => 'festival-logo.webp']);
    $widget = Widget::factory()->create([
        'owner_type' => Festival::class,
        'owner_id' => $festival->id,
    ]);

    $promoter->delete();

    expect(Festival::find($festival->id))->toBeNull()
        ->and(Widget::find($widget->id))->toBeNull();

    Storage::disk('do')->assertMissing('festival-logo.webp');
});

test('a listing shows the festival mark unless told not to', function () {
    $festival = Festival::factory()->create();

    $listing = Listing::factory()->create([
        'promoter_id' => $festival->promoter_id,
        'festival_id' => $festival->id,
    ]);

    expect($listing->fresh()->show_festival_mark)->toBeTrue()
        ->and((new Listing)->show_festival_mark)->toBeTrue();
});
