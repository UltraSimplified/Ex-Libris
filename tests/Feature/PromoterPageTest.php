<?php

use App\Models\Listing;
use App\Models\Promoter;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

test('a promoter\'s page lists only what that promoter has on', function () {
    $promoter = Promoter::factory()->create(['name' => 'Stroud Sounds']);
    $theirs = Listing::factory()->create(['promoter_id' => $promoter->id]);
    Listing::factory()->create(['promoter_id' => Promoter::factory()]);
    Listing::factory()->create();

    $this->get(route('Promoter', [$promoter->id, 'stroud-sounds']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Listings')
            ->where('title', 'Listings for Stroud Sounds.')
            ->has('listings', 1)
            ->where('listings.0.id', $theirs->id)
            ->where('listings.0.promoter.name', 'Stroud Sounds')
        );
});

test('a promoter\'s page leaves out events that have finished', function () {
    $promoter = Promoter::factory()->create();
    Listing::factory()->create([
        'promoter_id' => $promoter->id,
        'start_date' => now()->subWeek(),
        'end_date' => now()->subDays(2),
    ]);

    $this->get(route('Promoter', [$promoter->id, 'any']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('listings', 0));
});

test('a promoter that does not exist is not found', function () {
    $this->get(route('Promoter', [99999, 'nobody']))->assertNotFound();
});

test('a promoter\'s page is given its banner, about and contact details', function () {
    $promoter = Promoter::factory()->create([
        'name' => 'Stroud Sounds',
        'about' => 'Gigs in odd places.',
        'phone' => '01453 654321',
        'email' => 'hello@stroudsounds.test',
    ]);
    $promoter->forceFill(['banner' => 'brands/sounds.webp'])->save();

    $this->get(route('Promoter', [$promoter->id, 'stroud-sounds']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('promoter.name', 'Stroud Sounds')
            ->where('promoter.banner', 'brands/sounds.webp')
            ->where('promoter.about', 'Gigs in odd places.')
            ->where('promoter.phone', '01453 654321')
            ->where('promoter.email', 'hello@stroudsounds.test')
        );
});

test('a promoter\'s website link is tagged as Good on Paper\'s referral', function () {
    $promoter = Promoter::factory()->create(['website' => 'https://sounds.example.com/']);

    $this->get(route('Promoter', [$promoter->id, 'sounds']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where(
            'promoter.website_link',
            "https://sounds.example.com/?utm_source=goodonpaper&utm_medium=affiliate&utm_campaign=promoter-{$promoter->id}"
        ));
});

test('a promoter with no website has no website link', function () {
    $promoter = Promoter::factory()->create(['website' => null]);

    $this->get(route('Promoter', [$promoter->id, 'nowhere']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('promoter.website_link', null));
});

test('a listing\'s own page is given its promoter', function () {
    $promoter = Promoter::factory()->create(['name' => 'Stroud Sounds']);
    $listing = Listing::factory()->create(['promoter_id' => $promoter->id]);

    $this->get(route('Listing', [$listing->id, 'event']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('listing.promoter.id', $promoter->id)
            ->where('listing.promoter.name', 'Stroud Sounds')
        );
});
