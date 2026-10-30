<?php

use App\Models\Listing;
use App\Services\Listings\ListingLineUp;

/*
 * What the line-up shows on the listing page, in order: each artist's name,
 * then "playlist" and "video" for the players under it.
 */
const LINE_UP_ORDER = <<<'JS'
    [...document.querySelectorAll('.listing-line-up')]
        .find((lineUp) => lineUp.offsetParent !== null)
        .querySelectorAll('.line-up-artist')
        .length === 0 ? '' : [...[...document.querySelectorAll('.listing-line-up')]
        .find((lineUp) => lineUp.offsetParent !== null)
        .querySelectorAll('.line-up-artist')]
        .map((artist) => [
            artist.querySelector('h3').innerText.trim(),
            ...[...artist.querySelectorAll('iframe')].map((iframe) =>
                /bandcamp/.test(iframe.src) ? 'playlist' : 'video'),
        ].join(' + '))
        .join(' | ')
    JS;

test('the listing page shows the line-up artist by artist: name, playlist, then video', function (int $width) {
    $lineUps = app(ListingLineUp::class);
    $listing = Listing::factory()->create([
        'line_up' => json_encode(array_merge(
            json_decode($lineUps->fromSingleEmbeds('Sun Ra Arkestra', '<iframe src="https://bandcamp.com/EmbeddedPlayer/album=1"></iframe>', 'https://youtu.be/abc123'), true),
            json_decode($lineUps->fromSingleEmbeds('The Comet Is Coming', null, 'https://vimeo.com/76979871'), true),
        )),
    ]);

    visit(route('Listing', [$listing->id, $listing->slug], absolute: false))
        ->resize($width, 1000)
        ->assertScript(LINE_UP_ORDER, 'SUN RA ARKESTRA + playlist + video | THE COMET IS COMING + video')
        ->assertNoJavaScriptErrors();
})->with([
    'desktop' => [1280],
    'phone' => [390],
]);

test('a listing with no line-up shows none', function () {
    $listing = Listing::factory()->create(['line_up' => null]);

    visit(route('Listing', [$listing->id, $listing->slug], absolute: false))
        ->assertCount('.line-up-artist', 0)
        ->assertNoJavaScriptErrors();
});
