<?php

use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Owners can put a logo, a banner or a cover photo up themselves. The files
 * are cropped to the same sizes the Nova ImageCropper fields produce, so
 * both entry points write pictures the front end can use interchangeably.
 */
function storedSize(string $filename): array
{
    $image = Image::decodeBinary(Storage::disk('do')->get($filename));

    return [$image->width(), $image->height()];
}

test('a promoter owner can upload a logo and a banner', function () {
    Storage::fake('do');

    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('dashboard.promoters.update', $promoter), [
            'name' => $promoter->name,
            'logo' => UploadedFile::fake()->image('logo.png', 900, 300),
            'banner' => UploadedFile::fake()->image('banner.jpg', 2000, 500),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard.promoters.index'));

    $promoter->refresh();

    expect($promoter->logo)->toEndWith('.webp');
    expect($promoter->banner)->toEndWith('.webp');

    Storage::disk('do')->assertExists($promoter->logo);
    Storage::disk('do')->assertExists($promoter->banner);

    expect(storedSize($promoter->logo))->toBe([512, 512]);
    expect(storedSize($promoter->banner))->toBe([1280, 320]);
});

test('a new picture replaces the one it succeeds', function () {
    Storage::fake('do');
    Storage::disk('do')->put('old-logo.webp', 'not really an image');

    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create(['logo' => 'old-logo.webp']);

    $this->actingAs($user)
        ->put(route('dashboard.promoters.update', $promoter), [
            'name' => $promoter->name,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertSessionHasNoErrors();

    expect($promoter->fresh()->logo)->not->toBe('old-logo.webp');
    Storage::disk('do')->assertMissing('old-logo.webp');
});

test('a promoter picture can be removed', function () {
    Storage::fake('do');
    Storage::disk('do')->put('old-banner.webp', 'not really an image');

    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create(['banner' => 'old-banner.webp']);

    $this->actingAs($user)
        ->put(route('dashboard.promoters.update', $promoter), [
            'name' => $promoter->name,
            'remove_banner' => 'on',
        ])
        ->assertSessionHasNoErrors();

    expect($promoter->fresh()->banner)->toBeNull();
    Storage::disk('do')->assertMissing('old-banner.webp');
});

test('pictures are left alone when the form sends no file', function () {
    Storage::fake('do');
    Storage::disk('do')->put('kept.webp', 'not really an image');

    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create([
        'logo' => 'kept.webp',
        'banner' => 'kept.webp',
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.promoters.update', $promoter), ['name' => 'Renamed'])
        ->assertSessionHasNoErrors();

    expect($promoter->fresh())
        ->name->toBe('Renamed')
        ->logo->toBe('kept.webp')
        ->banner->toBe('kept.webp');

    Storage::disk('do')->assertExists('kept.webp');
});

test('a picture cannot be claimed by posting its filename', function () {
    Storage::fake('do');

    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create(['logo' => null]);

    $this->actingAs($user)
        ->from(route('dashboard.promoters.edit', $promoter))
        ->put(route('dashboard.promoters.update', $promoter), [
            'name' => $promoter->name,
            'logo' => 'somebody-elses-picture.webp',
        ])
        ->assertSessionHasErrors('logo');

    expect($promoter->fresh()->logo)->toBeNull();
});

test('a venue owner can upload and remove a cover photo', function () {
    Storage::fake('do');

    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create(['cover_photo' => null]);

    $this->actingAs($user)
        ->put(route('dashboard.venues.update', $venue), [
            'name' => $venue->name,
            'cover_photo' => UploadedFile::fake()->image('hall.jpg', 800, 800),
        ])
        ->assertSessionHasNoErrors();

    $cover = $venue->refresh()->cover_photo;

    expect($cover)->toEndWith('.webp');
    expect(storedSize($cover))->toBe([800, 800]);

    $this->actingAs($user)
        ->put(route('dashboard.venues.update', $venue), [
            'name' => $venue->name,
            'remove_cover_photo' => 'on',
        ])
        ->assertSessionHasNoErrors();

    expect($venue->fresh()->cover_photo)->toBeNull();
    Storage::disk('do')->assertMissing($cover);
});

test('a cover photo must be an image', function () {
    Storage::fake('do');

    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create(['cover_photo' => null]);

    $this->actingAs($user)
        ->from(route('dashboard.venues.edit', $venue))
        ->put(route('dashboard.venues.update', $venue), [
            'name' => $venue->name,
            'cover_photo' => UploadedFile::fake()->create('plan.pdf', 40, 'application/pdf'),
        ])
        ->assertSessionHasErrors('cover_photo');

    expect($venue->fresh()->cover_photo)->toBeNull();
});

test('a colour left unset is saved as nothing rather than as black', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create(['brand_colour' => '#ff0000']);

    $this->actingAs($user)
        ->put(route('dashboard.promoters.update', $promoter), [
            'name' => $promoter->name,
            'brand_colour' => '',
            'accent_colour1' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($promoter->fresh())
        ->brand_colour->toBeNull()
        ->accent_colour1->toBeNull();
});

test('a cover photo keeps its own shape, brought down to the banner width', function () {
    Storage::fake('do');

    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create(['cover_photo' => null]);

    $this->actingAs($user)
        ->put(route('dashboard.venues.update', $venue), [
            'name' => $venue->name,
            'cover_photo' => UploadedFile::fake()->image('printshop.jpg', 2560, 958),
        ])
        ->assertSessionHasNoErrors();

    expect(storedSize($venue->refresh()->cover_photo))->toBe([1280, 479]);
});
