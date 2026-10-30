<?php

use App\Models\Advert;
use App\Models\AdvertEvent;
use App\Nova\Advert as NovaAdvert;
use App\Nova\Cards\AdvertReach;
use App\Services\Adverts\AdvertReport;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Http\Requests\NovaRequest;

beforeEach(function () {
    Storage::fake('do');
});

/**
 * Events for an advert, all on one day.
 *
 * @param  array<string, mixed>  $attributes
 */
function advertEventsOn(Advert $advert, string $type, int $count, string $day, array $attributes = []): void
{
    AdvertEvent::factory()->count($count)->create([
        'advert_id' => $advert->id,
        'type' => $type,
        'created_at' => $day.' 12:00:00',
        ...$attributes,
    ]);
}

/**
 * A square image and a wide one, so the report can be seen to pick one.
 */
function storeCreatives(): void
{
    $square = imagecreatetruecolor(40, 40);
    $wide = imagecreatetruecolor(160, 40);

    ob_start();
    imagepng($square);
    Storage::disk('do')->put('adverts/mobile.png', ob_get_clean());

    ob_start();
    imagepng($wide);
    Storage::disk('do')->put('adverts/desktop.png', ob_get_clean());
}

test('a signed link downloads the report as a Good on Paper PDF', function () {
    $advert = Advert::factory()->create(['title' => 'Stroud Arts Festival']);
    $report = app(AdvertReport::class);

    $response = $this->get($report->downloadUrl($advert));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload($report->filename($advert));

    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-')
        ->and($report->filename($advert))->toBe('good-on-paper-advert-report-stroud-arts-festival-'.today()->toDateString().'.pdf');
});

test('the report cannot be had without a signed link', function () {
    $advert = Advert::factory()->create();

    $this->get(route('exports.advert-report', $advert))->assertForbidden();
    $this->get(app(AdvertReport::class)->downloadUrl($advert).'x')->assertForbidden();
});

test('the report counts the advert\'s own events across its run, and nothing outside it', function () {
    $advert = Advert::factory()->create(['run_from' => '2026-10-01', 'run_to' => '2026-10-10']);
    $another = Advert::factory()->create();

    advertEventsOn($advert, 'impression', 5, '2026-10-02');
    advertEventsOn($advert, 'impression', 3, '2026-10-10');
    advertEventsOn($advert, 'click', 2, '2026-10-02');
    advertEventsOn($advert, 'impression', 9, '2026-09-30');
    advertEventsOn($advert, 'impression', 9, '2026-10-11');
    advertEventsOn($another, 'impression', 9, '2026-10-02');

    $this->travelTo('2026-10-20 09:00');
    $data = app(AdvertReport::class)->for($advert);

    expect($data['totals']['impressions'])->toBe(8)
        ->and($data['totals']['clicks'])->toBe(2)
        ->and($data['totals']['rate'])->toBe(25.0)
        ->and($data['run']['days'])->toBe(10)
        ->and($data['run']['running'])->toBeFalse()
        ->and($data['series']['period'])->toBe('day')
        ->and($data['series']['rows'])->toHaveCount(10)
        ->and($data['series']['rows'][1])->toMatchArray(['label' => 'Fri 2 Oct', 'impressions' => 5, 'clicks' => 2, 'bar' => 100.0])
        ->and($data['totals']['best']['label'])->toBe('Fri 2 Oct');
});

test('a run still going is reported to today, and says so', function () {
    $this->travelTo('2026-10-15 09:00');
    $advert = Advert::factory()->create(['run_from' => '2026-10-01', 'run_to' => '2026-11-09']);
    advertEventsOn($advert, 'impression', 4, '2026-10-14');

    $data = app(AdvertReport::class)->for($advert);

    expect($data['run']['running'])->toBeTrue()
        ->and($data['run']['days'])->toBe(15)
        ->and($data['run']['until']->toDateString())->toBe('2026-10-15')
        ->and($data['series']['rows'])->toHaveCount(15);
});

test('a long run is charted a week at a time', function () {
    $this->travelTo('2026-12-31 09:00');
    $advert = Advert::factory()->create(['run_from' => '2026-10-01', 'run_to' => '2026-11-30']);
    advertEventsOn($advert, 'impression', 2, '2026-10-01');
    advertEventsOn($advert, 'impression', 3, '2026-10-04');
    advertEventsOn($advert, 'impression', 7, '2026-10-05');

    $series = app(AdvertReport::class)->for($advert)['series'];

    expect($series['period'])->toBe('week')
        ->and($series['rows'][0])->toMatchArray(['label' => 'w/c 1 Oct', 'impressions' => 5])
        ->and($series['rows'][1])->toMatchArray(['label' => 'w/c 5 Oct', 'impressions' => 7])
        ->and($series['rows'])->toHaveCount(10);
});

test('an advert yet to start says nothing has run', function () {
    $this->travelTo('2026-10-15 09:00');
    $advert = Advert::factory()->create(['run_from' => '2026-11-01', 'run_to' => '2026-11-30']);

    $data = app(AdvertReport::class)->for($advert);

    expect($data['run']['started'])->toBeFalse()
        ->and($data['series']['rows'])->toBe([])
        ->and(view('reports.advert', $data)->render())->toContain('Nothing has run yet.');
});

test('the report splits the figures by device, place and page', function () {
    $this->travelTo('2026-10-15 09:00');
    $advert = Advert::factory()->create(['run_from' => '2026-10-01', 'run_to' => '2026-10-31']);
    advertEventsOn($advert, 'impression', 6, '2026-10-02', ['variant' => 'desktop', 'page' => '/', 'locality_bucket' => 'stroud']);
    advertEventsOn($advert, 'impression', 2, '2026-10-02', ['variant' => 'mobile', 'page' => '/listings', 'locality_bucket' => null]);
    advertEventsOn($advert, 'click', 1, '2026-10-02', ['variant' => 'mobile', 'locality_bucket' => 'stroud']);

    $data = app(AdvertReport::class)->for($advert);

    expect($data['devices'])->toBe([
        ['label' => 'Desktop', 'impressions' => 6, 'clicks' => 0, 'rate' => 0.0, 'share' => 75.0],
        ['label' => 'Mobile', 'impressions' => 2, 'clicks' => 1, 'rate' => 50.0, 'share' => 25.0],
    ])
        ->and(array_column($data['places'], 'label'))->toBe(['Stroud', 'Unknown'])
        ->and($data['pages'])->toBe([
            ['page' => '/', 'impressions' => 6, 'share' => 75.0],
            ['page' => '/listings', 'impressions' => 2, 'share' => 25.0],
        ]);
});

test('the report shows the square mobile creative, or the desktop one when there is no other', function () {
    storeCreatives();
    $both = Advert::factory()->create(['mobile_image' => 'adverts/mobile.png', 'desktop_image' => 'adverts/desktop.png']);
    $desktopOnly = Advert::factory()->create(['mobile_image' => null, 'desktop_image' => 'adverts/desktop.png']);

    $size = fn (Advert $advert): array => array_slice(getimagesizefromstring(base64_decode(
        explode(',', app(AdvertReport::class)->for($advert)['advert']['creative'])[1]
    )), 0, 2);

    expect($size($both))->toBe([40, 40])
        ->and($size($desktopOnly))->toBe([160, 40]);
});

test('the report quotes the campaign', function () {
    $advert = Advert::factory()->create(['campaign' => 'Live at the Prince Albert', 'owner' => 'For Your Information']);

    expect(view('reports.advert', app(AdvertReport::class)->for($advert))->render())
        ->toContain('“Live at the Prince Albert” · For Your Information');
});

test('each advert on the dashboard card carries a signed link to its report', function () {
    $advert = Advert::factory()->create(['title' => 'Los Fastidios']);

    $slide = (new AdvertReach)->jsonSerialize()['adverts'][0];

    expect($slide['report'])->toContain('/exports/adverts/'.$advert->id.'/report')
        ->and($slide['report'])->toContain('signature=')
        ->and($slide['reportFilename'])->toStartWith('good-on-paper-advert-report-los-fastidios-');
});

test('an advert\'s page offers its report for download', function () {
    $advert = Advert::factory()->create(['title' => 'Los Fastidios']);
    $request = NovaRequest::create('/nova-api/adverts/'.$advert->id);

    $field = collect((new NovaAdvert($advert))->fields($request))
        ->first(fn ($field): bool => ($field->name ?? null) === 'Report');
    $field->resolveForDisplay($advert);

    expect($field->value)->toContain('class="gop-report-button"')
        ->and($field->value)->toContain('/exports/adverts/'.$advert->id.'/report')
        ->and($field->value)->toContain('download="good-on-paper-advert-report-los-fastidios-')
        ->and($field->isShownOnDetail($request, $advert))->toBeTrue()
        ->and($field->isShownOnIndex($request, $advert))->toBeFalse();
});
