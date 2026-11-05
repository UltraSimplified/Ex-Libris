<?php

use App\Models\Feature;
use App\Models\FeatureRead;
use App\Models\Tag;
use App\Models\User;

test('a feature page reports how long it was read once the reader leaves it', function () {
    $feature = Feature::factory()->create();

    $page = visit(route('feature', [$feature->id, $feature->slug], absolute: false))
        ->assertSee($feature->title);

    $page->wait(3);
    $page->navigate('/');
    $page->wait(1);

    $day = FeatureRead::query()->sole();

    expect($day->feature_id)->toBe($feature->id)
        ->and($day->read_count)->toBe(1)
        ->and($day->seconds_read)->toBeGreaterThanOrEqual(2);
});

test('the Features card shows the reading time by category and the most read', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));

    $archive = Feature::factory()->create([
        'title' => 'From the archive',
        'tag_id' => Tag::factory()->create(['tag' => 'Archives'])->id,
    ]);
    FeatureRead::factory()->create(['feature_id' => $archive->id, 'read_count' => 30, 'seconds_read' => 7500, 'reached_end_count' => 12]);

    visit('/office')
        ->assertSee('2h 5m')
        ->assertSee('Archives · 2h 5m (100%)')
        ->assertSee('Most read')
        ->assertSee('From the archive')
        ->assertSee('30 reads · 4m 10s each · 40% reached the end')
        ->assertNoJavaScriptErrors();
});
