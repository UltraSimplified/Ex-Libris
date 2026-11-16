<?php

use App\Models\Festival;
use App\Models\Listing;
use App\Models\Promoter;
use Inertia\Testing\AssertableInertia;

/**
 * A festival's page heads its programme with its own banner and details
 * and a line naming its promoter. While anything in it is to come, the
 * programme is what is left, by day; once it has all been, the whole of it,
 * under a line saying so.
 */
beforeEach(function () {
    $this->withoutVite();
});

/**
 * Riverside Fest 2027, from Friday 4 to Sunday 6 June, one event a day
 * and one that runs all three.
 *
 * @return array{festival: Festival, friday: Listing, saturday: Listing, sunday: Listing, weekend: Listing}
 */
function riversideFest(): array
{
    $promoter = Promoter::factory()->create(['name' => 'Northgate Promotions']);
    $festival = Festival::factory()->for($promoter)->create([
        'name' => 'Riverside Fest 2027',
        'about' => 'Three days by the water.',
        'website' => 'https://riverside.example',
        'banner' => 'riverside-banner.webp',
        'logo' => 'riverside-logo.webp',
    ]);

    $listing = fn (string $title, string $start, ?string $end = null): Listing => Listing::factory()->create([
        'title' => $title,
        'start_date' => $start,
        'end_date' => $end,
        'promoter_id' => $promoter->id,
        'festival_id' => $festival->id,
    ]);

    return [
        'festival' => $festival,
        'friday' => $listing('Opening Night', '2027-06-04'),
        'saturday' => $listing('Saturday Session', '2027-06-05'),
        'sunday' => $listing('Closing Party', '2027-06-06'),
        'weekend' => $listing('Riverside Exhibition', '2027-06-04', '2027-06-06'),
    ];
}

test('the header is the festival\'s own, naming its promoter', function () {
    ['festival' => $festival] = riversideFest();
    $this->travelTo('2027-05-01 10:00');

    $this->get(route('Festival', [$festival->id, 'riverside-fest-2027']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Listings')
            ->where('title', 'Listings for Riverside Fest 2027.')
            ->where('festival.name', 'Riverside Fest 2027')
            ->where('festival.about', 'Three days by the water.')
            ->where('festival.banner', 'riverside-banner.webp')
            ->where('festival.website_link', fn (string $link): bool => str_starts_with($link, 'https://riverside.example') && str_contains($link, 'festival-'.$festival->id))
            ->where('festival.promoter.name', 'Northgate Promotions')
            ->where('finished', false)
            ->has('listings', 4)
        );
});

test('the festival is found by its id whatever the slug says', function () {
    ['festival' => $festival] = riversideFest();

    $this->get(route('Festival', [$festival->id, 'an-old-name']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('festival.id', $festival->id));
});

test('halfway through, the page shows what is left, by day from today', function () {
    ['festival' => $festival, 'saturday' => $saturday, 'sunday' => $sunday, 'weekend' => $weekend] = riversideFest();
    $this->travelTo('2027-06-05 10:00');

    $this->get(route('Festival', [$festival->id, 'riverside-fest-2027']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('finished', false)
            ->where('firstDay', '2027-06-05')
            ->where('dayHeadings', true)
            ->where('listings', fn ($listings): bool => collect($listings)->pluck('id')->sort()->values()->all()
                === collect([$saturday->id, $sunday->id, $weekend->id])->sort()->values()->all())
            ->where('listings.2.id', $sunday->id)
            ->where('listings.2.next_day', '2027-06-06')
        );
});

test('once it has all been, the page shows the whole programme from its first day, and says so', function () {
    ['festival' => $festival, 'friday' => $friday, 'sunday' => $sunday] = riversideFest();
    $this->travelTo('2027-07-01 10:00');

    $this->get(route('Festival', [$festival->id, 'riverside-fest-2027']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('finished', true)
            ->where('firstDay', '2027-06-04')
            ->has('listings', 4)
            ->where('listings.0.next_day', '2027-06-04')
            ->where('listings.3.id', $sunday->id)
            ->where('listings.3.next_day', '2027-06-06')
            ->where('listings', fn ($listings): bool => collect($listings)->contains('id', $friday->id))
        );
});

test('a festival with nothing in it yet has not finished', function () {
    $festival = Festival::factory()->create();

    $this->get(route('Festival', [$festival->id, 'any']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('listings', 0)
            ->where('finished', false)
        );
});

test('a festival that does not exist is not found', function () {
    $this->get(route('Festival', [99999, 'nobody']))->assertNotFound();
});
