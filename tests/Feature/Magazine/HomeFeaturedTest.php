<?php

use App\Models\Feature;
use App\Models\Tag;
use App\Models\User;
use App\Nova\Feature as NovaFeature;
use Inertia\Testing\AssertableInertia;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Http\Requests\NovaRequest;

beforeEach(function () {
    $this->withoutVite();

    // The front end hard-codes tag id 5 as "reviews"; every other tag is a feature.
    $this->reviewsTag = Tag::factory()->reviews()->create();
    $this->featuresTag = Tag::factory()->create(['tag' => 'Interviews']);
});

/**
 * Features of the given tag, the first the newest, a day apart.
 *
 * @return list<Feature>
 */
function featuresDaysApart(Tag $tag, int $count, array $attributes = []): array
{
    return collect(range(0, $count - 1))
        ->map(fn (int $day) => Feature::factory()->create([
            'tag_id' => $tag->id,
            'created_at' => now()->subDays($day),
            ...$attributes,
        ]))
        ->all();
}

test('a feature is not featured until somebody picks it', function () {
    expect(Feature::factory()->create()->fresh()->featured)->toBeFalse();
});

test('with nothing featured, the home page leads with the newest feature and lists the next three', function () {
    [$newest, $second, $third, $fourth] = featuresDaysApart($this->featuresTag, 5);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->has('featured', 1)
            ->where('featured.0.id', $newest->id)
            ->has('features', 3)
            ->where('features.0.id', $second->id)
            ->where('features.2.id', $fourth->id)
        );
});

test('the features picked for the home page lead it in the order they are sorted in the office, whatever their age', function () {
    [$newest, $second, $third, $fourth, $fifth] = featuresDaysApart($this->featuresTag, 5);
    $second->forceFill(['featured' => true, 'sort_order' => 10])->save();
    $fourth->forceFill(['featured' => true, 'sort_order' => 20])->save();

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('featured', 2)
            ->where('featured.0.id', $fourth->id)
            ->where('featured.1.id', $second->id)
            ->where('featured.0.featured', true)
        );
});

test('the office lists features in their sort order, highest first, and lets them be dragged', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
    [$newest, $older] = featuresDaysApart($this->featuresTag, 2);
    $newest->forceFill(['sort_order' => 1])->save();
    $older->forceFill(['sort_order' => 2])->save();

    $resources = $this->getJson('/nova-api/features')->assertOk()->json('resources');

    expect(collect($resources)->pluck('id.value')->all())->toBe([$older->id, $newest->id])
        ->and($resources[0]['sortable'] ?? null)->not->toBeNull();
});

test('a new feature goes to the top of the office order', function () {
    $older = Feature::factory()->create(['tag_id' => $this->featuresTag->id]);
    $older->forceFill(['sort_order' => 50])->save();

    $new = Feature::factory()->create(['tag_id' => $this->featuresTag->id]);

    expect($new->fresh()->sort_order)->toBeGreaterThan($older->fresh()->sort_order);
});

test('a featured article is not listed again below', function () {
    [$newest, $second, $third, $fourth, $fifth] = featuresDaysApart($this->featuresTag, 5);
    $newest->forceFill(['featured' => true])->save();
    $third->forceFill(['featured' => true])->save();

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('features', 3)
            ->where('features.0.id', $second->id)
            ->where('features.1.id', $fourth->id)
            ->where('features.2.id', $fifth->id)
        );
});

test('a featured review leads the home page and is not listed again below', function () {
    [$review, $olderReview] = featuresDaysApart($this->reviewsTag, 2);
    $review->forceFill(['featured' => true])->save();

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('featured', 1)
            ->where('featured.0.id', $review->id)
            ->has('features', 1)
            ->where('features.0.id', $olderReview->id)
            ->missing('reviews')
        );
});

test('every featured article leads the home page, however many there are', function () {
    featuresDaysApart($this->featuresTag, 6, ['featured' => true]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('featured', 6)
            ->has('features', 0)
        );
});

test('the home page renders with no features at all', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('featured', 0)
            ->has('features', 0)
        );
});

test('the features page still leads with its newest feature, featured or not', function () {
    [$newest, $second] = featuresDaysApart($this->featuresTag, 2);
    $second->forceFill(['featured' => true])->save();

    $this->get(route('Features'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Features')
            ->where('latest.id', $newest->id)
        );
});

test('the office can pick a feature for the home page', function () {
    $field = collect((new NovaFeature(new Feature))->fields(app(NovaRequest::class)))
        ->first(fn ($field): bool => $field->attribute === 'featured');

    expect($field)->toBeInstanceOf(Boolean::class)
        ->and($field->helpText)->toContain('top of the home page');
});
