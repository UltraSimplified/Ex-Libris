<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Services\Listings\ListingLineUp;

/**
 * Hand the thumbnail picker a file, from inside the page.
 *
 * attach() only offers the browser a path on this machine, which the browser
 * refuses, so the picker is given its file here and told it changed, which is
 * all a real pick amounts to.
 */
function chooseThumbnailScript(): string
{
    return <<<'JS'
        () => {
            const png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
            const bytes = Uint8Array.from(atob(png), (character) => character.charCodeAt(0));
            const transfer = new DataTransfer();
            transfer.items.add(new File([bytes], 'poster.png', { type: 'image/png' }));

            const input = document.querySelector('#thumbnail');
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        JS;
}

/**
 * Choosing a thumbnail emptied the description.
 *
 * The text areas were bound with :value, which Vue writes back to the DOM on
 * every render of the component, changed or not. Picking a thumbnail sets the
 * preview, which renders the component, and the text areas were put back to
 * what the server sent: nothing at all, on a new listing.
 *
 * Only a real browser typing into a real form shows it. The page props never
 * change, so nothing a feature test can inspect was ever wrong.
 */
test('choosing a thumbnail leaves the text areas as they were typed', function () {
    Category::factory()->create();

    $this->actingAs(User::factory()->agreed()->create());

    $page = visit(route('dashboard.listings.create', absolute: false))
        ->assertSee('Thumbnail image')
        ->fill('#body', 'A very loud evening in Nottingham.')
        // Opening the panel renders the component too, so the embeds are
        // typed after it and have to survive both that and the thumbnail.
        ->click('Additional media')
        ->click('Add artist')
        ->fill('#line_up_0_name', 'Sun Ra Arkestra')
        ->fill('#line_up_0_playlist', '<iframe src="https://bandcamp.com/EmbeddedPlayer/album=1"></iframe>')
        ->fill('#line_up_0_video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    $page->script(chooseThumbnailScript());

    $page->assertPresent('img[src^="blob:"]')
        ->assertValue('#body', 'A very loud evening in Nottingham.')
        ->assertValue('#line_up_0_name', 'Sun Ra Arkestra')
        ->assertValue('#line_up_0_playlist', '<iframe src="https://bandcamp.com/EmbeddedPlayer/album=1"></iframe>')
        ->assertValue('#line_up_0_video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->assertNoJavaScriptErrors();
});

/**
 * The other half of the same fix: the text areas now only start with the
 * saved text, so an edit has to still arrive with it and keep it.
 */
test('an existing listing opens with its text and keeps it when a thumbnail is chosen', function () {
    $user = User::factory()->agreed()->create();

    $listing = Listing::factory()->for($user)->create([
        'body' => 'Already written, and saved.',
        'line_up' => app(ListingLineUp::class)->fromSingleEmbeds(
            'Sun Ra Arkestra',
            '<iframe src="https://bandcamp.com/EmbeddedPlayer/album=2"></iframe>',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ),
    ]);
    [$artist] = app(ListingLineUp::class)->artists($listing->fresh()->line_up);

    $this->actingAs($user);

    $page = visit(route('dashboard.listings.edit', $listing, absolute: false))
        ->assertValue('#body', 'Already written, and saved.')
        ->assertValue('#line_up_0_name', 'Sun Ra Arkestra')
        ->assertValue('#line_up_0_playlist', $artist['playlist'])
        ->assertValue('#line_up_0_video', $artist['video']);

    $page->script(chooseThumbnailScript());

    $page->assertPresent('img[src^="blob:"]')
        ->assertValue('#body', 'Already written, and saved.')
        ->assertValue('#line_up_0_name', 'Sun Ra Arkestra')
        ->assertValue('#line_up_0_playlist', $artist['playlist'])
        ->assertValue('#line_up_0_video', $artist['video'])
        ->assertNoJavaScriptErrors();
});
