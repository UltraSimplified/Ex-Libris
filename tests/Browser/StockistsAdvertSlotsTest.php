<?php

use App\Models\Advert;
use App\Models\Stockist;

/**
 * Stockists named in order, each with an address.
 */
function stockists(int $count): void
{
    foreach (range(1, $count) as $number) {
        Stockist::forceCreate([
            'name' => sprintf('Stockist %02d', $number),
            'address' => $number.' High Street',
            'town' => 'Stroud',
            'postcode' => 'GL5 1AA',
            'link' => null,
        ]);
    }
}

/*
 * The stockists list's rows, as "stockist" or "advert", in order.
 */
const STOCKIST_ROWS = <<<'JS'
    [...document.querySelector('h1').closest('.md\\:pl-48').children[1].children]
        .map((row) => (row.classList.contains('listing-advert') ? 'advert' : 'stockist'))
        .join(',')
    JS;

/*
 * How far below its stockist's name the address starts.
 */
const ADDRESS_DROP = <<<'JS'
    (() => {
        const row = [...document.querySelectorAll('p')].find((p) => p.textContent.includes('Stockist 01'));
        const [name, address] = row.children;

        // Below means past the foot of the name, not merely lower: on one
        // line the smaller address still starts a little further down.
        return address.getBoundingClientRect().top >= name.getBoundingClientRect().bottom - 1 ? 'below' : 'beside';
    })()
    JS;

test('stockists break for an advert half as often as the listings do, never last', function () {
    config(['adverts.listings_every' => 2]);
    Advert::factory()->count(2)->create([
        'desktop_image' => 'adverts/desktop.webp',
        'mobile_image' => 'adverts/mobile.webp',
    ]);
    stockists(9);

    visit('/magazine/stockists')
        ->assertScript(STOCKIST_ROWS, implode(',', [
            ...array_fill(0, 4, 'stockist'), 'advert',
            ...array_fill(0, 4, 'stockist'), 'advert',
            'stockist',
        ]))
        ->assertNoJavaScriptErrors();
});

test('stockists carry no adverts when the listings carry none', function () {
    config(['adverts.listings_every' => 0]);
    Advert::factory()->create([
        'desktop_image' => 'adverts/desktop.webp',
        'mobile_image' => 'adverts/mobile.webp',
    ]);
    stockists(12);

    visit('/magazine/stockists')
        ->assertScript(STOCKIST_ROWS, implode(',', array_fill(0, 12, 'stockist')));
});

test('a stockist\'s address sits below its name on a phone and on desktop alike', function () {
    stockists(1);

    visit('/magazine/stockists')->on()->mobile()
        ->assertScript(ADDRESS_DROP, 'below');

    visit('/magazine/stockists')
        ->assertScript(ADDRESS_DROP, 'below');
});
