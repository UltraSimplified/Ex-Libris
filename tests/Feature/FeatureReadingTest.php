<?php

use App\Models\Feature;
use App\Models\FeatureRead;
use App\Models\User;

const READING_BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1';

/**
 * @param  array{seconds?: int, newRead?: bool, reachedEnd?: bool}  $report
 */
function reportReading(Feature $feature, array $report = [], string $userAgent = READING_BROWSER): void
{
    test()->withHeader('User-Agent', $userAgent)
        ->postJson(route('features.reading', $feature), [
            'seconds' => 90,
            'newRead' => true,
            'reachedEnd' => false,
            ...$report,
        ]);
}

test('a reader\'s report adds a read and its time to the feature\'s day', function () {
    $feature = Feature::factory()->create();

    reportReading($feature, ['seconds' => 90]);

    $day = FeatureRead::query()->sole();

    expect($day->feature_id)->toBe($feature->id)
        ->and($day->read_on->toDateString())->toBe(today()->toDateString())
        ->and($day->read_count)->toBe(1)
        ->and($day->seconds_read)->toBe(90)
        ->and($day->reached_end_count)->toBe(0);
});

test('a later report from the same read adds its time but not another read', function () {
    $feature = Feature::factory()->create();

    reportReading($feature, ['seconds' => 90, 'newRead' => true]);
    reportReading($feature, ['seconds' => 30, 'newRead' => false, 'reachedEnd' => true]);

    $day = FeatureRead::query()->sole();

    expect($day->read_count)->toBe(1)
        ->and($day->seconds_read)->toBe(120)
        ->and($day->reached_end_count)->toBe(1);
});

test('each day keeps its own totals', function () {
    $feature = Feature::factory()->create();

    reportReading($feature);
    $this->travel(1)->day();
    reportReading($feature);

    expect(FeatureRead::query()->pluck('read_count')->all())->toBe([1, 1]);
});

test('a crawler or the Office\'s own staff are not counted', function () {
    $feature = Feature::factory()->create();

    reportReading($feature, userAgent: 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

    $this->actingAs(User::factory()->create(['email' => User::OFFICE_EMAILS[0]]));
    reportReading($feature);

    expect(FeatureRead::query()->count())->toBe(0);
});

test('a draft has no readers to count', function () {
    $draft = Feature::factory()->draft()->create();

    test()->withHeader('User-Agent', READING_BROWSER)
        ->postJson(route('features.reading', $draft), ['seconds' => 90, 'newRead' => true, 'reachedEnd' => false])
        ->assertNotFound();

    expect(FeatureRead::query()->count())->toBe(0);
});

test('a report claiming more than half an hour is refused', function () {
    $feature = Feature::factory()->create();

    test()->withHeader('User-Agent', READING_BROWSER)
        ->postJson(route('features.reading', $feature), ['seconds' => FeatureRead::LONGEST_REPORT + 1, 'newRead' => true, 'reachedEnd' => false])
        ->assertUnprocessable();

    expect(FeatureRead::query()->count())->toBe(0);
});

test('a counted report is answered with no content, as a beacon expects', function () {
    $feature = Feature::factory()->create();

    test()->withHeader('User-Agent', READING_BROWSER)
        ->postJson(route('features.reading', $feature), ['seconds' => 5, 'newRead' => true, 'reachedEnd' => false])
        ->assertNoContent();
});
