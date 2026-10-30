<?php

use App\Models\Feature;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();

    // The front end hard-codes tag id 5 as "reviews"; every other tag is a feature.
    $this->reviewsTag = Tag::factory()->reviews()->create();
    $this->featuresTag = Tag::factory()->create(['tag' => 'Interviews']);
});

test('reviews are left out of the lead and listed among the features below it', function () {
    $feature = Feature::factory()->create([
        'tag_id' => $this->featuresTag->id,
        'created_at' => now()->subDay(),
    ]);

    $review = Feature::factory()->create([
        'tag_id' => $this->reviewsTag->id,
        'created_at' => now()->subDays(2),
    ]);

    $this->get(route('Features'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Features')
            ->where('latest.id', $feature->id)
            ->has('more.data', 1)
            ->where('more.data.0.id', $review->id)
            ->missing('reviews')
        );
});

test('features with no tag are excluded from both lists', function () {
    // `where('tag_id', '!=', 5)` never matches NULL in SQL, so an untagged feature
    // silently disappears from the site even though it is the most recent record.
    $untagged = Feature::factory()->untagged()->create([
        'created_at' => now(),
    ]);

    $feature = Feature::factory()->create([
        'tag_id' => $this->featuresTag->id,
        'created_at' => now()->subDay(),
    ]);

    $this->get(route('Features'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Features')
            ->where('latest.id', $feature->id)
            ->has('features', 0)
            ->has('more.data', 0)
        );

    expect($untagged->fresh())->not->toBeNull();
});

test('the newest feature is surfaced separately from the rest', function () {
    $features = collect(range(0, 3))->map(fn (int $day) => Feature::factory()->create([
        'tag_id' => $this->featuresTag->id,
        'created_at' => now()->subDays($day),
    ]));

    $this->get(route('Features'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Features')
            ->where('latest.id', $features->first()->id)
            ->has('features', 3)
        );
});

test('at most four features are fetched, leaving three after the latest is split off', function () {
    Feature::factory()->count(8)->create(['tag_id' => $this->featuresTag->id]);

    $this->get(route('Features'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Features')
            ->has('features', 3)
        );
});

/**
 * The newest feature is the one the home page and the index lead with. Left
 * without a byline, it took the whole panel down with it, so it is credited
 * to Good on Paper on both pages instead.
 */
test('a newest feature with no writer is credited to Good on Paper on the home page and the index', function () {
    User::factory()->create(['id' => Feature::GOOD_ON_PAPER_USER_ID, 'name' => 'Good on Paper']);

    $feature = Feature::factory()->unbylined()->create([
        'tag_id' => $this->featuresTag->id,
        'created_at' => now(),
    ]);

    // The home page leads with a list, of the featured articles or else the newest.
    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('featured.0.id', $feature->id)
            ->where('featured.0.user.name', 'Good on Paper')
        );

    $this->get(route('Features'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('latest.id', $feature->id)
            ->where('latest.user.name', 'Good on Paper')
        );
});

test('the features after the lead come six at a time, none of them repeating the lead', function () {
    $features = collect(range(1, 17))->map(fn (int $day) => Feature::factory()->create([
        'tag_id' => $this->featuresTag->id,
        'created_at' => now()->subDays($day),
    ]));

    $lead = $features->take(4)->pluck('id')->all();
    $seen = [];

    foreach ([1 => 6, 2 => 6, 3 => 1] as $number => $count) {
        $response = $this->get(route('Features', ['page' => $number]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('more.data', $count));

        $seen = [...$seen, ...array_column($response->viewData('page')['props']['more']['data'], 'id')];
    }

    expect($seen)->toHaveCount(13)
        ->and(array_unique($seen))->toHaveCount(13)
        ->and(array_intersect($seen, $lead))->toBeEmpty()
        ->and($seen)->toBe($features->skip(4)->pluck('id')->values()->all());
});

test('the index renders with no features at all', function () {
    $this->get(route('Features'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Features')
            ->where('latest', null)
            ->has('features', 0)
            ->has('more.data', 0)
        );
});

test('the home page lists reviews among its features rather than on their own', function () {
    $feature = Feature::factory()->create([
        'tag_id' => $this->featuresTag->id,
        'created_at' => now()->subDay(),
    ]);

    $review = Feature::factory()->create([
        'tag_id' => $this->reviewsTag->id,
        'created_at' => now()->subDays(2),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->where('featured.0.id', $feature->id)
            ->has('features', 1)
            ->where('features.0.id', $review->id)
            ->missing('reviews')
        );
});
