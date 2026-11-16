<?php

use App\Models\Festival;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

/**
 * Search engines are told a festival is a festival: when it runs, where,
 * who puts it on and the events within it. Each of those events names the
 * festival it is part of, and the sitemap lists the festivals with
 * anything in them.
 */
beforeEach(function () {
    $this->withoutVite();
    Cache::forget('sitemap.xml');
});

test('a festival\'s page describes it as a festival made up of its events', function () {
    $this->travelTo('2027-05-01 10:00');
    $promoter = Promoter::factory()->create(['name' => 'Northgate Promotions']);
    $festival = Festival::factory()->for($promoter)->create(['name' => 'Riverside Fest 2027', 'about' => 'Three days by the water.']);
    $cornExchange = Venue::factory()->create(['name' => 'The Corn Exchange']);
    $opening = Listing::factory()->create([
        'title' => 'Opening Night',
        'start_date' => '2027-06-04',
        'venue_id' => $cornExchange->id,
        'promoter_id' => $promoter->id,
        'festival_id' => $festival->id,
    ]);
    Listing::factory()->create([
        'title' => 'Riverside Exhibition',
        'start_date' => '2027-06-04',
        'end_date' => '2027-06-06',
        'venue_id' => $cornExchange->id,
        'promoter_id' => $promoter->id,
        'festival_id' => $festival->id,
    ]);

    $this->get(route('Festival', [$festival->id, 'riverside-fest-2027']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('meta.title', 'Riverside Fest 2027')
            ->where('meta.description', 'Three days by the water.')
            ->where('meta.jsonLd.@type', 'Festival')
            ->where('meta.jsonLd.name', 'Riverside Fest 2027')
            ->where('meta.jsonLd.startDate', '2027-06-04')
            ->where('meta.jsonLd.endDate', '2027-06-06')
            ->where('meta.jsonLd.organizer.name', 'Northgate Promotions')
            ->where('meta.jsonLd.location', [
                ['@type' => 'Place', 'name' => 'The Corn Exchange', 'address' => $cornExchange->address ?? 'The Corn Exchange'],
            ])
            ->has('meta.jsonLd.subEvent', 2)
            ->where('meta.jsonLd.subEvent', fn ($events): bool => collect($events)->contains(fn (array $event): bool => $event === [
                '@type' => 'Event',
                'name' => 'Opening Night',
                'url' => route('Listing', ['id' => $opening->id, 'slug' => $opening->slug]),
                'startDate' => '2027-06-04',
            ]))
        );
});

test('a festival event names the festival as the event it is part of', function () {
    $festival = Festival::factory()->create(['name' => 'Riverside Fest 2027']);
    $listing = Listing::factory()->create([
        'promoter_id' => $festival->promoter_id,
        'festival_id' => $festival->id,
    ]);
    $plain = Listing::factory()->create();

    $this->get(route('Listing', [$listing->id, $listing->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('meta.jsonLd.superEvent', [
            '@type' => 'Festival',
            'name' => 'Riverside Fest 2027',
            'url' => route('Festival', ['id' => $festival->id, 'slug' => 'riverside-fest-2027']),
        ]));

    $this->get(route('Listing', [$plain->id, $plain->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('meta.jsonLd.superEvent'));
});

test('the sitemap lists festivals with something in them, and not empty ones', function () {
    $listed = Festival::factory()->create(['name' => 'Riverside Fest 2027']);
    Listing::factory()->create(['promoter_id' => $listed->promoter_id, 'festival_id' => $listed->id]);
    $empty = Festival::factory()->create(['name' => 'Riverside Fest 2028']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>'.route('Festival', ['id' => $listed->id, 'slug' => 'riverside-fest-2027']).'</loc>', false)
        ->assertDontSee(route('Festival', ['id' => $empty->id, 'slug' => 'riverside-fest-2028']));
});
