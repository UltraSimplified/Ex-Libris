<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Venue;

/**
 * Where a click in the middle of the first listing's row element would go:
 * the link it lands on, or the label of the button.
 */
function landsOn(string $selector): string
{
    return <<<JS
        (() => {
            const row = document.querySelector('.divide-silver > div');
            const target = row.querySelector('{$selector}');
            target.scrollIntoView({ block: 'center', behavior: 'instant' });
            const box = target.getBoundingClientRect();
            const hit = document
                .elementFromPoint(box.left + box.width / 2, box.top + box.height / 2)
                .closest('a, button');

            return hit.getAttribute('href') ?? hit.getAttribute('aria-label');
        })()
        JS;
}

function villageFete(): Listing
{
    return Listing::factory()
        ->for(Category::factory()->create(['name' => 'Music', 'slug' => 'music']))
        ->for(Venue::factory()->create(['name' => 'Stroud Brewery']))
        ->create(['title' => 'Village Fete', 'ticket_price' => 'Free', 'start_date' => today()->addDay()]);
}

test('the whole of a listing row leads to the listing, and shades when pointed at', function () {
    $listing = villageFete();
    $address = '/listings/'.$listing->id.'/'.$listing->slug;

    visit('/listings')
        ->assertScript(landsOn('img'), $address)
        ->assertScript(landsOn('h1 + h2'), $address)
        ->assertScript(landsOn('h1'), $address)
        ->hover('.divide-silver > div > div')
        ->assertScript("getComputedStyle(document.querySelector('.divide-silver > div > div')).backgroundColor", 'rgb(247, 247, 247)')
        ->click('Village Fete')
        ->assertPathIs($address)
        ->assertNoJavaScriptErrors();
});

test('the tag, the heart and the venue on a listing row still go where they say', function () {
    $listing = villageFete();

    visit('/listings')
        ->assertScript(landsOn('a[href*="?genre="]'), '/listings?genre=music')
        ->assertScript(landsOn('[aria-label*="bookmark"]'), 'Click to bookmark this listing')
        ->assertScript(landsOn('a[href^="/listings/venue/"]'), '/listings/venue/'.$listing->venue->id.'/stroud-brewery')
        ->click('Stroud Brewery')
        ->assertPathIs('/listings/venue/'.$listing->venue->id.'/stroud-brewery')
        ->assertNoJavaScriptErrors();
});

test('the map pin by the venue is drawn on whole pixels, so no edge of it is cut off', function () {
    villageFete();

    visit('/listings')
        ->assertScript(<<<'JS'
            (() => {
                const pin = document.querySelector('.divide-silver a[href^="/listings/venue/"] svg');
                const box = pin.getBoundingClientRect();

                return [box.left % 1, box.top % 1, box.width, box.height].join('x');
            })()
            JS, '0x0x12x16')
        ->assertNoJavaScriptErrors();
});
