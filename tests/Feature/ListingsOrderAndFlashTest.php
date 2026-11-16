<?php

use App\Models\Advert;
use App\Models\Listing;

beforeEach(function () {
    $this->withoutVite();

    // A Wednesday, so "this week" runs on to Sunday the 18th.
    $this->travelTo('2026-10-14 10:00');
});

function listingFromTo(string $title, string $start, ?string $end = null): Listing
{
    return Listing::factory()->create(['title' => $title, 'start_date' => $start, 'end_date' => $end]);
}

/**
 * The titles a listings page shows, in its order.
 *
 * @return list<string>
 */
function listingTitlesOn(string $url): array
{
    return array_column(test()->get($url)->assertOk()->viewData('page')['props']['listings'], 'title');
}

test('the listings come in the order they finish, what ends today first', function (string $url) {
    listingFromTo('Runs to the end of the month', '2026-10-14', '2026-10-31');
    listingFromTo('One night only', '2026-10-14');
    listingFromTo('Ends on Saturday', '2026-10-01', '2026-10-17');
    listingFromTo('Last day of a long run', '2026-09-01', '2026-10-14');

    expect(listingTitlesOn($url))->toBe([
        'Last day of a long run',
        'One night only',
        'Ends on Saturday',
        'Runs to the end of the month',
    ]);
})->with([
    'today' => '/listings/today',
    'all' => '/listings',
    'this week' => '/listings/thisweek',
]);

test('pages carry the live adverts and how often the listings break for one', function () {
    config(['adverts.listings_every' => 3]);
    $live = Advert::factory()->create(['title' => 'Live']);
    Advert::factory()->create(['active' => false]);

    $this->get('/listings')->assertInertia(fn ($page) => $page
        ->where('advertEvery', 3)
        ->has('liveAdverts', 1)
        ->where('liveAdverts.0.id', $live->id)
    );
});

test('the listings break for an advert every five events unless told otherwise', function () {
    expect(config('adverts.listings_every'))->toBe(5);
});

test('a negative interval is taken as no adverts in the listings', function () {
    config(['adverts.listings_every' => -2]);

    $this->get('/listings')->assertInertia(fn ($page) => $page->where('advertEvery', 0));
});
