<?php

use App\Models\Advert;
use App\Models\Category;
use App\Models\Festival;
use App\Models\Listing;
use App\Models\User;
use App\Models\Venue;

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

/**
 * A festival listing's card draws the festival's mark and its "Part of"
 * line, so every page that lists it, and its own page, sends the festival
 * along with it, and whether its mark is wanted.
 */
test('a festival listing carries its festival and its mark setting', function (string $page) {
    $festival = Festival::factory()->create([
        'name' => 'Riverside Fest 2027',
        'logo' => 'riverside.webp',
        'brand_background_colour' => '#1e90ff',
    ]);
    $venue = Venue::factory()->create(['name' => 'The Corn Exchange']);
    $category = Category::factory()->create(['slug' => 'music']);
    $listing = Listing::factory()->create([
        'title' => 'Riverside Opening Night',
        'start_date' => '2026-10-14',
        'venue_id' => $venue->id,
        'category_id' => $category->id,
        'promoter_id' => $festival->promoter_id,
        'festival_id' => $festival->id,
        'show_festival_mark' => false,
    ]);
    $reader = User::factory()->create();
    $reader->bookmarks()->attach($listing);

    $url = match ($page) {
        'all' => '/listings',
        'today' => '/listings/today',
        'this week' => '/listings/thisweek',
        'venue' => "/listings/venue/{$venue->id}/the-corn-exchange",
        'promoter' => "/listings/promoter/{$festival->promoter_id}/promoter",
        'category' => '/listings/category/music',
        'bookmarked' => '/listings/bookmarked',
        'its own page' => "/listings/{$listing->id}/riverside-opening-night",
    };

    $props = $this->actingAs($reader)->get($url)->assertOk()->viewData('page')['props'];
    $card = $page === 'its own page' ? $props['listing'] : $props['listings'][0];

    expect($card['festival'])->toMatchArray([
        'id' => $festival->id,
        'name' => 'Riverside Fest 2027',
        'logo' => 'riverside.webp',
        'mark_background' => '#1e90ff',
    ])->and($card['show_festival_mark'])->toBeFalse();
})->with(['all', 'today', 'this week', 'venue', 'promoter', 'category', 'bookmarked', 'its own page']);
