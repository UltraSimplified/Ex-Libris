<?php

use App\Models\Feature;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;

test('a feature can be created', function () {
    $feature = Feature::factory()->create([
        'title' => 'The Analogue Revival',
        'subject' => 'Independent print',
    ]);

    expect($feature->exists)->toBeTrue();

    $this->assertDatabaseHas('features', [
        'id' => $feature->id,
        'title' => 'The Analogue Revival',
        'subject' => 'Independent print',
    ]);
});

test('a slug is generated from the title when none is supplied', function () {
    $feature = Feature::factory()->create(['title' => 'The Analogue Revival']);

    expect($feature->slug)->toBe('the-analogue-revival');
});

test('an explicitly supplied slug is not overwritten', function () {
    $feature = Feature::factory()
        ->withSlug('a-hand-picked-slug')
        ->create(['title' => 'The Analogue Revival']);

    expect($feature->slug)->toBe('a-hand-picked-slug');
});

test('the slug is not regenerated when the title later changes', function () {
    $feature = Feature::factory()->create(['title' => 'Original Title']);

    $feature->title = 'A Completely Different Title';
    $feature->save();

    // The saving hook only fills an empty slug, so existing URLs stay stable.
    expect($feature->fresh()->slug)->toBe('original-title');
});

test('the published date is cast to an immutable date', function () {
    $feature = Feature::factory()->create(['published' => '2026-03-14']);

    expect($feature->published)
        ->toBeInstanceOf(CarbonImmutable::class)
        ->and($feature->published->toDateString())->toBe('2026-03-14');
});

test('a feature belongs to the account that wrote it', function () {
    $writer = User::factory()->writer()->create(['name' => 'Jo Bloggs']);

    $feature = Feature::factory()->for($writer)->create();

    expect($feature->user)->not->toBeNull()
        ->and($feature->user->name)->toBe('Jo Bloggs');
});

test('a writer has many features', function () {
    $writer = User::factory()->writer()->create();

    Feature::factory()->count(3)->for($writer)->create();

    expect($writer->features)->toHaveCount(3);
});

/**
 * The byline is nullable, so an article can outlive the account that
 * wrote it rather than being deleted along with them.
 */
test('a feature can have no byline at all', function () {
    $feature = Feature::factory()->unbylined()->create();

    expect($feature->user_id)->toBeNull()
        ->and($feature->user)->toBeNull();
});

test('a feature belongs to a tag', function () {
    $tag = Tag::factory()->create(['tag' => 'Interviews']);

    $feature = Feature::factory()->for($tag)->create();

    expect($feature->tag)->not->toBeNull()
        ->and($feature->tag->tag)->toBe('Interviews');
});

test('a feature can be created without a writer or tag', function () {
    $feature = Feature::factory()->untagged()->unbylined()->create();

    expect($feature->user)->toBeNull()
        ->and($feature->tag)->toBeNull();
});

test('the body is stored as valid json', function () {
    $feature = Feature::factory()->create();

    expect(json_decode($feature->body, true))->toBeArray();
});
