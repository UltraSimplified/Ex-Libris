<?php

use App\Models\Feature;
use App\Models\User;
use App\Services\Images\FeatureGallery;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(FeatureGallery::DISK);
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
});

/**
 * Choose photos in a Photojournal on the form, all at once, as picking
 * several in the file dialog does. attach() only offers the browser a path
 * on this machine, which it refuses, so each photo is handed to the picker
 * from the page, which is told it changed. Each is the same single pixel, as
 * nothing here looks at the photo, and drawing them on a canvas instead can
 * hold the page up for many seconds.
 */
function choosePhotosScript(int $gallery, int $count, string $prefix): string
{
    return <<<JS
        (() => {
            const pixel = Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='), (character) => character.charCodeAt(0));
            const transfer = new DataTransfer();

            for (let n = 1; n <= {$count}; n++) {
                transfer.items.add(new File([pixel], `{$prefix}-\${n}.png`, { type: 'image/png' }));
            }

            const input = document.querySelectorAll('.nova-gallery')[{$gallery}].querySelector('.nova-gallery__input');
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        })()
        JS;
}

/**
 * Answer the gallery's uploads in the page, each with a photo of its own,
 * after a moment as the server would. The test server cannot take a file
 * upload, and what is under test is what the form does with the answers.
 */
function stubGalleryUploadsScript(): string
{
    return <<<'JS'
        (() => {
            let stored = 0;
            const request = Nova.request.bind(Nova);

            Nova.request = (...options) => {
                const client = request(...options);

                if (! client.__galleryStub) {
                    client.__galleryStub = true;
                    client.interceptors.request.use(answerUpload);
                }

                return client;
            };

            function answerUpload(config) {
                if (! String(config.url).endsWith('/nova-vendor/gallery/upload')) {
                    return config;
                }

                const key = 'cStub' + String(++stored).padStart(10, '0');

                config.adapter = () => new Promise((resolve) => setTimeout(() => resolve({
                    data: {
                        image: `features/galleries/${key}.webp`,
                        thumb: `features/galleries/${key}-thumb.webp`,
                        width: 800, height: 600, alt: null, caption: null,
                    },
                    status: 200, statusText: 'OK', headers: {}, config,
                }), 50));

                return config;
            }
        })()
        JS;
}

/**
 * The photos chosen in each Photojournal on the form, in order, by name of
 * the file stored.
 */
function photojournalsOnTheForm(): string
{
    return <<<'JS'
        JSON.stringify([...document.querySelectorAll('.nova-gallery')].map((gallery) =>
            gallery.querySelectorAll('.nova-gallery__item:not(.is-uploading)').length))
        JS;
}

/*
 * As Alex did it: a Photojournal added and filled, then a second added and
 * filled. The second's photos go in the second and the first keeps its own,
 * twenty apiece.
 */
test('two photojournals filled one after the other keep their own photos', function () {
    $feature = Feature::factory()->create(['body' => '[]']);

    $page = visit("/office/resources/features/{$feature->id}/edit")
        ->click('[dusk="toggle-layouts-dropdown-or-add-default"]')
        ->click('[dusk="add-gallery"]');

    $page->script(stubGalleryUploadsScript());
    $page->script(choosePhotosScript(0, 20, 'first'));

    $page->assertMissing('.nova-gallery__item.is-uploading')
        ->assertScript(photojournalsOnTheForm(), '[20]')
        ->click('[dusk="toggle-layouts-dropdown-or-add-default"]')
        ->click('[dusk="add-gallery"]');

    $page->script(choosePhotosScript(1, 20, 'second'));

    $page->assertMissing('.nova-gallery__item.is-uploading')
        ->assertScript(photojournalsOnTheForm(), '[20,20]')
        ->assertScript(<<<'JS'
            new Set([...document.querySelectorAll('.nova-gallery__item:not(.is-uploading) img')].map((image) => image.src)).size
            JS, 40)
        ->assertNoJavaScriptErrors();
});

/*
 * Twenty-five chosen at once: the first twenty go in, the rest are left out
 * with a word to say so, and the gallery takes no more.
 */
test('a photojournal takes no more than twenty photos', function () {
    $feature = Feature::factory()->create(['body' => '[]']);

    $page = visit("/office/resources/features/{$feature->id}/edit")
        ->click('[dusk="toggle-layouts-dropdown-or-add-default"]')
        ->click('[dusk="add-gallery"]');

    $page->script(stubGalleryUploadsScript());
    $page->script(choosePhotosScript(0, 25, 'many'));

    $page->assertMissing('.nova-gallery__item.is-uploading')
        ->assertScript(photojournalsOnTheForm(), '[20]')
        ->assertSee('A gallery holds up to 20 photos, so 5 were left out.')
        ->assertMissing('.nova-gallery__input')
        ->assertNoJavaScriptErrors();
});
