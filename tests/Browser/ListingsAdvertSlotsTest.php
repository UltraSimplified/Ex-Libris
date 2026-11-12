<?php

use App\Models\Advert;
use App\Models\Listing;

/**
 * Listings that are all on today, so each page shows every one of them.
 */
function listingsOnToday(int $count): void
{
    foreach (range(1, $count) as $number) {
        Listing::factory()->create([
            'title' => 'Listing '.$number,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays($number)->toDateString(),
        ]);
    }
}

/**
 * Live adverts with their creatives, as the Office requires them.
 */
function liveAdvertsWithCreatives(int $count): void
{
    Advert::factory()->count($count)->create([
        'desktop_image' => 'adverts/desktop.webp',
        'mobile_image' => 'adverts/mobile.webp',
    ]);
}

/*
 * The rows of the listings grid, as "heading", "listing" or "advert", in
 * order.
 */
const LISTING_ROWS = <<<'JS'
    [...document.querySelector('.divide-silver').children]
        .map((row) => {
            if (row.querySelector('.listing-heading')) {
                return 'heading';
            }

            return row.querySelector('.listing-advert') ? 'advert' : 'listing';
        })
        .join(',')
    JS;

/*
 * How wide each creative is drawn, or "hidden": the listings slot's mobile
 * and desktop images, then the sponsorship panel's.
 */
const CREATIVE_WIDTHS = <<<'JS'
    (() => {
        const width = (image) => getComputedStyle(image).display === 'none'
            ? 'hidden'
            : Math.round(image.getBoundingClientRect().width);
        const slot = [...document.querySelectorAll('.listing-advert img')];
        const band = [...document.querySelectorAll('a[href*="/click?v="] img')]
            .filter((image) => !image.closest('.listing-advert'));

        return [...slot, ...band].map(width).join(' | ');
    })()
    JS;

test('an advert from the live line-up goes in after every so many listings, never last', function () {
    config(['adverts.listings_every' => 5]);
    liveAdvertsWithCreatives(2);
    listingsOnToday(12);

    visit('/listings')
        ->assertCount('.listing-advert', 2)
        ->assertScript(LISTING_ROWS, implode(',', [
            'heading',
            ...array_fill(0, 5, 'listing'), 'advert',
            ...array_fill(0, 5, 'listing'), 'advert',
            'listing', 'listing',
        ]))
        ->assertSee('Paid advertisement')
        ->assertNoJavaScriptErrors();
});

test('an advert due at the end of a day goes in before the next day\'s heading, not after it', function () {
    $this->travelTo('2026-11-12 12:00');
    config(['adverts.listings_every' => 5]);
    liveAdvertsWithCreatives(1);
    Listing::factory()->count(5)->create(['start_date' => '2026-11-13']);
    Listing::factory()->count(2)->create(['start_date' => '2026-11-14']);

    visit('/listings')
        ->assertScript(LISTING_ROWS, implode(',', [
            'heading', ...array_fill(0, 5, 'listing'),
            'advert',
            'heading', 'listing', 'listing',
        ]))
        ->assertNoJavaScriptErrors();
});

test('from 768px up an advert shows its desktop creative, below that its mobile one, and neither larger than it was made', function () {
    config(['adverts.listings_every' => 1]);
    liveAdvertsWithCreatives(1);
    Advert::factory()->sponsorship()->create();
    listingsOnToday(2);

    visit('/listings')
        ->assertCount('.listing-advert', 1)
        ->assertScript(CREATIVE_WIDTHS, 'hidden | 728 | hidden | 728')
        ->resize(700, 900)
        ->assertScript(CREATIVE_WIDTHS, '400 | hidden | 400 | hidden')
        ->assertNoJavaScriptErrors();
});

test('no advert is left dangling after the last listing', function () {
    config(['adverts.listings_every' => 5]);
    liveAdvertsWithCreatives(1);
    listingsOnToday(10);

    visit('/listings')->assertCount('.listing-advert', 1)->assertNoJavaScriptErrors();
});

test('the adverts in the listings take turns, and never include the sponsorship', function () {
    config(['adverts.listings_every' => 2]);
    liveAdvertsWithCreatives(3);
    $sponsorship = Advert::factory()->sponsorship()->create();
    listingsOnToday(9);

    visit('/listings')
        ->assertCount('.listing-advert', 4)
        ->assertScript(<<<JS
            (() => {
                const id = (anchor) => Number(anchor.getAttribute('href').match(/\/ad\/(\d+)\//)[1]);
                const panel = id(document.querySelector('.sponsorship-panel a[href^="/ad/"]'));
                const slots = [...document.querySelectorAll('.listing-advert a[href*="v=mobile"]')].map(id);

                return panel === {$sponsorship->id}
                    && !slots.includes(panel)
                    && new Set(slots.slice(0, 3)).size === 3
                    && slots[3] === slots[0];
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

test('with the interval set to nothing, the listings run on without adverts', function () {
    config(['adverts.listings_every' => 0]);
    liveAdvertsWithCreatives(2);
    listingsOnToday(12);

    visit('/listings')->assertCount('.listing-advert', 0)->assertNoJavaScriptErrors();
});

test('a listing finishing today is listed for today, with no flash over it', function () {
    Listing::factory()->create(['title' => 'One night only', 'start_date' => today()->toDateString(), 'end_date' => null]);

    visit('/listings/today')
        ->assertSee('One night only')
        ->assertDontSee('TODAY ONLY')
        ->assertNoJavaScriptErrors();
});

test('an advert with no creative is left out of the listings', function () {
    config(['adverts.listings_every' => 2]);
    Advert::factory()->create(['desktop_image' => null, 'mobile_image' => null]);
    listingsOnToday(5);

    visit('/listings')->assertCount('.listing-advert', 0)->assertSee('Listing 5');
});

test('an advert with only one of its two creatives takes its slots, left out at the other size', function (array $creatives, string $hiddenAt) {
    config(['adverts.listings_every' => 2]);
    Advert::factory()->create($creatives);
    listingsOnToday(5);

    visit('/listings')
        ->assertCount('.listing-advert', 2)
        ->assertAttributeContains('.listing-advert >> nth=0', 'class', $hiddenAt)
        ->assertSee('Listing 5');
})->with([
    'no desktop creative' => [['desktop_image' => null, 'mobile_image' => 'sponsors/mobile.gif'], 'md:hidden'],
    'no mobile creative' => [['desktop_image' => 'sponsors/desktop.gif', 'mobile_image' => null], 'max-md:hidden'],
]);
