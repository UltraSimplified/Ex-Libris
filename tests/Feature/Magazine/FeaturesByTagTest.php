<?php

use App\Models\Feature;
use App\Models\Tag;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();

    $this->arts = Tag::factory()->create(['tag' => 'Arts']);
    $this->music = Tag::factory()->create(['tag' => 'Music']);
});

test('a tag page lists only the features filed under it, newest first', function () {
    $newest = Feature::factory()->create(['tag_id' => $this->arts->id, 'created_at' => now()]);
    $older = Feature::factory()->create(['tag_id' => $this->arts->id, 'created_at' => now()->subDay()]);
    Feature::factory()->create(['tag_id' => $this->music->id, 'created_at' => now()]);

    $this->get(route('features.tag', 'arts'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Magazine/Features')
            ->where('title', 'Features / Arts')
            ->where('latest.id', $newest->id)
            ->has('features', 1)
            ->where('features.0.id', $older->id)
            ->has('more.data', 0)
        );
});

test('a tag page leads with its newest four and pages the rest in six at a time', function () {
    $features = collect(range(1, 11))->map(fn (int $day) => Feature::factory()->create([
        'tag_id' => $this->arts->id,
        'created_at' => now()->subDays($day),
    ]));
    Feature::factory()->count(3)->create(['tag_id' => $this->music->id]);

    $this->get(route('features.tag', 'arts'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('latest.id', $features[0]->id)
            ->has('features', 3)
            ->has('more.data', 6)
            ->where('more.data.0.id', $features[4]->id)
        );

    $this->get(route('features.tag', ['tag' => 'arts', 'page' => 2]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('more.data', 1)
            ->where('more.data.0.id', $features[10]->id)
        );
});

test('a tag page is found by a slug made from a tag name of more than one word', function () {
    Tag::factory()->create(['tag' => 'Photo Journals']);

    $this->get('/magazine/features/photo-journals')->assertOk();
});

test('a tag nobody has made is not found', function () {
    $this->get('/magazine/features/knitting')->assertNotFound();
});

test('a tag with no features still renders', function () {
    $this->get(route('features.tag', 'music'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('latest', null)
            ->has('features', 0)
        );
});

test('a feature carries its tag slug so its category can link to the tag page', function () {
    Feature::factory()->create(['tag_id' => $this->arts->id]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('featured.0.tag.slug', 'arts')
        );
});

test('an article still opens at its own address alongside the tag pages', function () {
    $feature = Feature::factory()->create(['tag_id' => $this->arts->id]);

    $this->get(route('feature', [$feature->id, $feature->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Magazine/Feature'));
});
