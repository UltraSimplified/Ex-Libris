<?php

use App\Models\Advert;
use App\Models\Feature;
use App\Models\Listing;
use App\Models\User;
use App\Nova\Feature as NovaFeature;
use Laravel\Nova\Http\Requests\NovaRequest;
use Whitecube\NovaFlexibleContent\Flexible;

test('a feature carries its listing, with the venue and category its row shows', function () {
    $listing = Listing::factory()->create(['title' => 'TEST ENTRY gig']);
    $feature = Feature::factory()->create(['listing_id' => $listing->id]);

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Magazine/Feature')
            ->where('feature.listing.id', $listing->id)
            ->where('feature.listing.venue.id', $listing->venue_id)
            ->where('feature.listing.category.id', $listing->category_id)
        );
});

test('a feature with no listing carries none', function () {
    $feature = Feature::factory()->create();

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('feature.listing', null));
});

test('a listing that has finished is left off the feature', function () {
    $listing = Listing::factory()->create([
        'start_date' => today()->subDays(3),
        'end_date' => today()->subDay(),
    ]);
    $feature = Feature::factory()->create(['listing_id' => $listing->id]);

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('feature.listing', null));
});

test('a listing on its last day is still shown', function () {
    $listing = Listing::factory()->create([
        'start_date' => today()->subDays(2),
        'end_date' => today(),
    ]);
    $feature = Feature::factory()->create(['listing_id' => $listing->id]);

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertInertia(fn ($page) => $page->where('feature.listing.id', $listing->id));
});

test('the office preview shows the listing too', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $listing = Listing::factory()->create();
    $feature = Feature::factory()->create(['listing_id' => $listing->id]);

    $this->actingAs($staff)
        ->get(route('features.preview', $feature))
        ->assertInertia(fn ($page) => $page->where('feature.listing.id', $listing->id));
});

test('an advert block reaches the feature page with the live in-list adverts to fill it', function () {
    $advert = Advert::factory()->create(['title' => 'TEST ENTRY advert']);
    $feature = Feature::factory()->create([
        'body' => json_encode([
            ['layout' => 'copy', 'key' => 'a1', 'attributes' => ['text' => '<p>Before</p>']],
            ['layout' => 'advert', 'key' => 'a2', 'attributes' => []],
        ]),
    ]);

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('feature.body.1.layout', 'advert')
            ->where('liveAdverts.0.id', $advert->id)
        );
});

/**
 * A feature whose body holds a Listing block for each listing given, and
 * nothing else.
 */
function featureWithListingBlocks(Listing ...$listings): Feature
{
    return Feature::factory()->create([
        'body' => json_encode(array_map(fn (Listing $listing): array => [
            'layout' => 'listing',
            'key' => 'block'.$listing->id,
            'attributes' => ['listing_id' => (string) $listing->id],
        ], $listings)),
    ]);
}

test('a listing block carries its listing, with the venue and category its row shows', function () {
    $first = Listing::factory()->create(['title' => 'TEST ENTRY first gig']);
    $second = Listing::factory()->create(['title' => 'TEST ENTRY second gig']);
    $feature = featureWithListingBlocks($first, $second);

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('feature.body.0.attributes.listing.id', $first->id)
            ->where('feature.body.0.attributes.listing.venue.id', $first->venue_id)
            ->where('feature.body.0.attributes.listing.category.id', $first->category_id)
            ->where('feature.body.1.attributes.listing.id', $second->id)
        );
});

test('a listing block whose listing has finished carries none', function () {
    $finished = Listing::factory()->create([
        'start_date' => today()->subDays(3),
        'end_date' => today()->subDay(),
    ]);
    $feature = featureWithListingBlocks($finished);

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('feature.body.0.attributes.listing', null));
});

test('a listing block left empty carries no listing', function () {
    $feature = Feature::factory()->create([
        'body' => json_encode([['layout' => 'listing', 'key' => 'empty', 'attributes' => []]]),
    ]);

    $this->get(route('feature', ['id' => $feature->id, 'slug' => $feature->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('feature.body.0.attributes.listing', null));
});

test('the office preview carries the listing blocks too', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $listing = Listing::factory()->create();
    $feature = featureWithListingBlocks($listing);

    $this->actingAs($staff)
        ->get(route('features.preview', $feature))
        ->assertInertia(fn ($page) => $page->where('feature.body.0.attributes.listing.id', $listing->id));
});

test('the listing block offers the events still to come, and keeps one already chosen that has finished', function () {
    $upcoming = Listing::factory()->create(['title' => 'TEST ENTRY upcoming']);
    $chosen = Listing::factory()->create(['start_date' => today()->subDays(3), 'end_date' => today()->subDay()]);
    $other = Listing::factory()->create(['start_date' => today()->subDays(3), 'end_date' => today()->subDay()]);
    $feature = featureWithListingBlocks($chosen);

    $flexible = collect((new NovaFeature($feature))->fields(NovaRequest::create('/nova-api/features', 'GET')))
        ->first(fn ($field): bool => $field instanceof Flexible);

    $options = collect(json_decode(json_encode($flexible), true)['layouts'])
        ->firstWhere('name', 'listing')['fields'][0]['options'];

    expect(collect($options)->pluck('value')->all())
        ->toContain($upcoming->id, $chosen->id)
        ->not->toContain($other->id);
});
