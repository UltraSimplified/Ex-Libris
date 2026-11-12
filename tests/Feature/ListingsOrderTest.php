<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\Venue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * The ids of the listings on a page, in the order it lists them.
 *
 * @return list<int>
 */
function listedIds(AssertableInertia $page): array
{
    return array_column($page->toArray()['props']['listings'], 'id');
}

test('a show opening in two days is listed with its opening day, ahead of a gig later on', function () {
    $this->travelTo('2026-11-12 12:00');
    $gig = Listing::factory()->create(['start_date' => '2026-11-20']);
    $show = Listing::factory()->create(['start_date' => '2026-11-14', 'end_date' => '2026-11-29']);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('listings.0.id', $show->id)
            ->where('listings.0.next_day', '2026-11-14')
            ->where('listings.1.id', $gig->id)
            ->where('listings.1.next_day', '2026-11-20')
        );
});

test('an exhibition already under way is listed with today, ahead of tomorrow\'s gig', function () {
    $this->travelTo('2026-11-12 12:00');
    $gig = Listing::factory()->create(['start_date' => '2026-11-13']);
    $exhibition = Listing::factory()->create(['start_date' => '2026-09-05', 'end_date' => '2026-11-30']);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('listings.0.id', $exhibition->id)
            ->where('listings.0.next_day', '2026-11-12')
            ->where('listings.1.id', $gig->id)
        );
});

test('a listing on specific days is listed with the next of them', function () {
    $this->travelTo('2026-11-10 12:00');
    $quiz = Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();
    $before = Listing::factory()->create(['start_date' => '2026-11-16']);
    $after = Listing::factory()->create(['start_date' => '2026-11-18']);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($quiz, $before, $after) {
            expect(listedIds($page))->toBe([$before->id, $quiz->id, $after->id]);

            return $page->where('listings.1.next_day', '2026-11-17');
        });
});

test('listings on the same day go by how soon their run ends', function () {
    $this->travelTo('2026-11-12 12:00');
    $closingLater = Listing::factory()->create(['start_date' => '2026-11-10', 'end_date' => '2026-11-22']);
    $closingTomorrow = Listing::factory()->create(['start_date' => '2026-09-05', 'end_date' => '2026-11-13']);
    $tonight = Listing::factory()->create(['start_date' => '2026-11-12']);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($tonight, $closingTomorrow, $closingLater) {
            expect(listedIds($page))->toBe([$tonight->id, $closingTomorrow->id, $closingLater->id]);

            return $page;
        });
});

test('specific days do not count towards how soon a run ends', function () {
    $this->travelTo('2026-11-12 12:00');
    $quiz = Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();
    $play = Listing::factory()->create(['start_date' => '2026-11-17', 'end_date' => '2026-11-18']);
    $gig = Listing::factory()->create(['start_date' => '2026-11-17']);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($gig, $play, $quiz) {
            expect(listedIds($page))->toBe([$gig->id, $play->id, $quiz->id]);

            return $page;
        });
});

test('listings on the same day that end together go by title', function () {
    $this->travelTo('2026-11-12 12:00');
    $shindig = Listing::factory()->create(['title' => 'Shindig', 'start_date' => '2026-11-14']);
    $weaving = Listing::factory()->create(['title' => 'Loom Weaving', 'start_date' => '2026-11-14']);

    $this->get(route('Listings'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($weaving, $shindig) {
            expect(listedIds($page))->toBe([$weaving->id, $shindig->id]);

            return $page;
        });
});

test('a chosen date range counts from its own first day', function () {
    $this->travelTo('2026-11-12 12:00');
    $gig = Listing::factory()->create(['start_date' => '2026-11-19']);
    $exhibition = Listing::factory()->create(['start_date' => '2026-11-01', 'end_date' => '2026-11-30']);

    $this->get('/listings/dates/2026-11-18/2026-11-30')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('listings.0.id', $exhibition->id)
            ->where('listings.0.next_day', '2026-11-18')
            ->where('listings.1.id', $gig->id)
        );
});

test('each page tells the browser today\'s date and the day its order counts from', function (string $path, string $firstDay) {
    $this->travelTo('2026-11-12 12:00');

    $this->get($path)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentDay', '2026-11-12')
            ->where('firstDay', $firstDay)
        );
})->with([
    'all listings' => ['/listings', '2026-11-12'],
    'this week' => ['/listings/thisweek', '2026-11-12'],
    'tomorrow' => ['/listings/tomorrow', '2026-11-13'],
    'next week' => ['/listings/nextweek', '2026-11-16'],
    'a chosen range' => ['/listings/dates/2026-11-18/2026-11-30', '2026-11-18'],
]);

test('a page about a single day is not headed by day', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('dayHeadings', false));
})->with([
    'today' => '/listings/today',
    'tomorrow' => '/listings/tomorrow',
    'a one-day range' => '/listings/dates/2026-11-20/2026-11-20',
]);

test('a page that spans days is headed by day', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('dayHeadings', true));
})->with([
    'all listings' => '/listings',
    'this week' => '/listings/thisweek',
    'next week' => '/listings/nextweek',
    'a chosen range' => '/listings/dates/2026-11-18/2026-11-30',
    'bookmarked listings' => '/listings/bookmarked',
    'a venue' => fn () => route('Venue', [Venue::factory()->create()->id, 'any'], absolute: false),
    'a promoter' => fn () => route('Promoter', [Promoter::factory()->create()->id, 'any'], absolute: false),
    'a category' => fn () => route('Category', Category::factory()->create()->slug, absolute: false),
]);
