<?php

use App\Models\Festival;
use App\Models\Listing;
use App\Models\User;
use App\Models\Venue;

/*
 * A festival listing's cover carries the festival's logo in its bottom
 * right, on the festival's background colour, a third of the cover wide,
 * wherever the cover is drawn - unless the listing turns it off or the
 * festival has no logo. The sold-out band stays over it.
 */
function festivalListing(array $listing = [], array $festival = []): Listing
{
    $festival = Festival::factory()->create([
        'name' => 'Riverside Fest 2027',
        'logo' => 'riverside.webp',
        'brand_background_colour' => '#1e90ff',
        ...$festival,
    ]);

    return Listing::factory()->create([
        'title' => 'Riverside Opening Night',
        'start_date' => today(),
        'venue_id' => Venue::factory()->create(['name' => 'The Corn Exchange'])->id,
        'promoter_id' => $festival->promoter_id,
        'festival_id' => $festival->id,
        ...$listing,
    ]);
}

/**
 * Where the mark sits on its cover, measured in the page: how far it is
 * from the cover's right and bottom edges, how wide it is next to the
 * cover, and what it is painted.
 *
 * @return array{right: float, bottom: float, share: float, colour: string}
 */
function markOnCover(mixed $page, string $cover): array
{
    return $page->script(<<<JS
        () => {
            const box = document.querySelector('{$cover}');
            const cover = box.querySelector('img').getBoundingClientRect();
            const mark = box.querySelector('[data-test="festival-mark"]');
            const tile = mark.getBoundingClientRect();

            return {
                right: Math.round(cover.right - tile.right),
                bottom: Math.round(cover.bottom - tile.bottom),
                share: Math.round((tile.width / cover.width) * 100) / 100,
                colour: getComputedStyle(mark).backgroundColor,
            };
        }
        JS);
}

test('the listing page puts the mark in the cover\'s bottom right, a third of its width', function () {
    $listing = festivalListing();

    $page = visit('/listings/'.$listing->id.'/riverside-opening-night')
        ->resize(390, 900)
        ->assertPresent('[data-test="festival-mark"]')
        ->assertNoJavaScriptErrors();

    expect(markOnCover($page, 'div.relative:has(> [data-test="festival-mark"])'))->toBe([
        'right' => 0,
        'bottom' => 0,
        'share' => 0.33,
        'colour' => 'rgb(30, 144, 255)',
    ]);
});

test('lists of listings put the mark on each cover at a third of its width', function (string $url, int $width) {
    $listing = festivalListing();

    $page = visit(str_replace('{venue}', (string) $listing->venue_id, $url))
        ->resize($width, 900)
        ->assertPresent('[data-test="festival-mark"]')
        ->assertNoJavaScriptErrors();

    expect(markOnCover($page, ':has(> [data-test="festival-mark"])'))->toMatchArray([
        'right' => 0,
        'bottom' => 0,
        'share' => 0.33,
    ]);
})->with([
    'all listings on a phone' => ['/listings', 390],
    'all listings on a desktop' => ['/listings', 1280],
    'a venue\'s page' => ['/listings/venue/{venue}/the-corn-exchange', 390],
]);

test('a sold-out festival listing shows both, the band over the mark', function () {
    $listing = festivalListing(['sold_out' => true]);

    $page = visit('/listings')
        ->assertPresent('[data-test="festival-mark"]')
        ->assertSee('Sold out')
        ->assertNoJavaScriptErrors();

    expect($page->script(<<<'JS'
        () => {
            const mark = document.querySelector('[data-test="festival-mark"]');
            const band = [...mark.parentElement.children].find((child) => child.textContent.trim() === 'Sold out');

            return Boolean(mark.compareDocumentPosition(band) & Node.DOCUMENT_POSITION_FOLLOWING);
        }
        JS))->toBeTrue();
});

test('a listing that turns the mark off, or whose festival has no logo, shows none', function (array $listing, array $festival) {
    $listing = festivalListing($listing, $festival);

    visit('/listings/'.$listing->id.'/riverside-opening-night')
        ->assertSee('Riverside Opening Night')
        ->assertNotPresent('[data-test="festival-mark"]')
        ->assertNoJavaScriptErrors();
})->with([
    'turned off' => [['show_festival_mark' => false], []],
    'no logo' => [[], ['logo' => null]],
]);

test('a festival listing says it is part of the festival, linking to its page', function () {
    $listing = festivalListing(['show_festival_mark' => false]);

    visit('/listings/'.$listing->id.'/riverside-opening-night')
        ->assertSeeIn('[data-test="part-of-festival"]', 'PART OF RIVERSIDE FEST 2027')
        ->assertPresent('[data-test="part-of-festival"] a[href="/listings/festival/'.$listing->festival_id.'/riverside-fest-2027"]')
        ->assertNoJavaScriptErrors();
});

test('the Backstage preview of a listing shows its mark as it will appear', function () {
    $owner = User::factory()->runsFestivals()->create();
    $listing = festivalListing(['user_id' => $owner->id]);
    $this->actingAs($owner);

    visit('/listings/'.$listing->id.'/preview')
        ->assertPresent('[data-test="festival-mark"]')
        ->assertNoJavaScriptErrors();
});
