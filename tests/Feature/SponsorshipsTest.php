<?php

use App\Models\Advert;
use App\Models\AdvertEvent;
use App\Nova\Advert as NovaAdvert;
use App\Services\Adverts\AdvertPool;
use App\Services\Adverts\AdvertReport;
use App\Services\Adverts\AdvertRotation;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Nova\Http\Requests\NovaRequest;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('do');
});

/**
 * The errors the Office gives an advert form, past the field rules: the
 * check that a sponsorship has both creatives.
 *
 * @param  array<string, mixed>  $input
 * @param  array<string, UploadedFile>  $files
 * @return array<string, list<string>>
 */
function sponsorshipFormErrors(array $input, array $files = [], ?Advert $editing = null): array
{
    $request = NovaRequest::create(
        $editing ? '/nova-api/adverts/'.$editing->id : '/nova-api/adverts',
        $editing ? 'PUT' : 'POST',
        $input,
        [],
        $files,
    );

    if ($editing) {
        $route = new Route('PUT', 'nova-api/{resource}/{resourceId}', []);
        $route->bind($request);
        $route->setParameter('resource', 'adverts');
        $route->setParameter('resourceId', $editing->id);
        $request->setRouteResolver(fn () => $route);
    }

    $validator = Validator::make([], []);
    $afterValidation = new ReflectionMethod(NovaAdvert::class, 'afterValidation');
    $afterValidation->invoke(null, $request, $validator);

    return $validator->errors()->toArray();
}

/**
 * Impressions or clicks for an advert, all on one day.
 *
 * @param  array<string, mixed>  $attributes
 */
function sponsorshipEventsOn(Advert $advert, string $type, int $count, string $day, array $attributes = []): void
{
    AdvertEvent::factory()->count($count)->create([
        'advert_id' => $advert->id,
        'type' => $type,
        'created_at' => $day.' 12:00:00',
        ...$attributes,
    ]);
}

test('an impression records where on the page it was seen', function () {
    $advert = Advert::factory()->sponsorship()->create();

    $this->postJson("/ad/{$advert->id}/impression", ['page' => '/listings', 'variant' => 'desktop', 'placement' => 'panel'])
        ->assertNoContent();

    expect($advert->events()->sole()->only(['type', 'page', 'variant', 'placement']))->toBe([
        'type' => 'impression',
        'page' => '/listings',
        'variant' => 'desktop',
        'placement' => 'panel',
    ]);
});

test('a placement that is not one of the two is not recorded', function () {
    $advert = Advert::factory()->create();

    $this->postJson("/ad/{$advert->id}/impression", ['variant' => 'mobile', 'placement' => 'sidebar']);

    expect($advert->events()->sole()->placement)->toBeNull();
});

test('a click records its placement and the page it came from', function () {
    $advert = Advert::factory()->create(['link' => 'https://example.com/']);

    $this->get("/ad/{$advert->id}/click?v=mobile&p=list&from=/listings/today")->assertRedirect();

    expect($advert->events()->sole()->only(['type', 'page', 'variant', 'placement']))->toBe([
        'type' => 'click',
        'page' => '/listings/today',
        'variant' => 'mobile',
        'placement' => 'list',
    ]);
});

test('a sponsorship\'s clicks are tagged as a sponsorship, whatever the link says', function () {
    $sponsorship = Advert::factory()->sponsorship()->create(['link' => 'https://example.com/', 'campaign' => 'autumn']);
    $inList = Advert::factory()->create(['link' => 'https://example.com/', 'campaign' => 'autumn']);

    $this->get("/ad/{$sponsorship->id}/click?v=desktop&p=list")
        ->assertRedirect('https://example.com/?utm_source=goodonpaper&utm_medium=sponsorship&utm_campaign=autumn');
    $this->get("/ad/{$inList->id}/click?v=desktop&p=panel")
        ->assertRedirect('https://example.com/?utm_source=goodonpaper&utm_medium=banner&utm_campaign=autumn');
});

test('the report splits the figures by placement, with older events as not recorded', function () {
    $this->travelTo('2026-10-15 09:00');
    $advert = Advert::factory()->sponsorship()->create(['run_from' => '2026-10-01', 'run_to' => '2026-10-31']);
    sponsorshipEventsOn($advert, 'impression', 6, '2026-10-02', ['placement' => 'panel']);
    sponsorshipEventsOn($advert, 'impression', 2, '2026-10-02', ['placement' => 'list']);
    sponsorshipEventsOn($advert, 'impression', 2, '2026-10-02', ['placement' => null]);
    sponsorshipEventsOn($advert, 'click', 3, '2026-10-02', ['placement' => 'panel']);

    $data = app(AdvertReport::class)->for($advert);

    expect($data['placements'])->toBe([
        ['label' => 'Sponsorship panel', 'impressions' => 6, 'clicks' => 3, 'rate' => 50.0, 'share' => 60.0],
        ['label' => 'In-list', 'impressions' => 2, 'clicks' => 0, 'rate' => 0.0, 'share' => 20.0],
        ['label' => 'Not recorded', 'impressions' => 2, 'clicks' => 0, 'rate' => 0.0, 'share' => 20.0],
    ])
        ->and($data['advert']['slot'])->toBe('Sponsorship panel');
});

test('an advert is labelled by the slot it takes', function () {
    expect(Advert::factory()->sponsorship()->make()->slot)->toBe('Sponsorship panel')
        ->and(Advert::factory()->make()->slot)->toBe('In-list');
});

test('deleting an advert archives it, keeping its events', function () {
    $advert = Advert::factory()->sponsorship()->create();
    AdvertEvent::factory()->count(3)->create(['advert_id' => $advert->id, 'type' => 'impression']);

    $advert->delete();

    expect(Advert::query()->find($advert->id))->toBeNull()
        ->and(Advert::withTrashed()->find($advert->id)->trashed())->toBeTrue()
        ->and(AdvertEvent::query()->where('advert_id', $advert->id)->count())->toBe(3)
        ->and(AdvertEvent::query()->first()->advert->is($advert))->toBeTrue();
});

test('an archived advert leaves both pools at once, and comes back when restored', function () {
    $sponsorship = Advert::factory()->sponsorship()->create();
    $inList = Advert::factory()->create();
    AdvertRotation::for(AdvertPool::Sponsorship)->live();
    AdvertRotation::for(AdvertPool::InList)->live();

    $sponsorship->delete();
    $inList->delete();

    expect(AdvertRotation::for(AdvertPool::Sponsorship)->live())->toBe([])
        ->and(AdvertRotation::for(AdvertPool::InList)->live())->toBe([]);

    $sponsorship->restore();

    expect(collect(AdvertRotation::for(AdvertPool::Sponsorship)->live())->pluck('id')->all())->toBe([$sponsorship->id]);
});

test('an archived advert\'s report can still be downloaded', function () {
    $advert = Advert::factory()->create(['title' => 'Stroud Arts Festival']);
    $report = app(AdvertReport::class);
    $link = $report->downloadUrl($advert);

    $advert->delete();

    $this->get($link)->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('a sponsorship cannot be saved without both creatives', function () {
    expect(sponsorshipFormErrors(['sponsorship' => '1']))->toHaveKeys(['desktop_image', 'mobile_image'])
        ->and(sponsorshipFormErrors(['sponsorship' => '1'], ['desktop_image' => UploadedFile::fake()->image('d.png', 728, 300)]))
        ->toHaveKey('mobile_image')
        ->not->toHaveKey('desktop_image');
});

test('a sponsorship with both creatives, uploaded now or already saved, passes', function () {
    $files = [
        'desktop_image' => UploadedFile::fake()->image('d.png', 728, 300),
        'mobile_image' => UploadedFile::fake()->image('m.png', 400, 300),
    ];
    $saved = Advert::factory()->create(['desktop_image' => 'sponsors/d.png', 'mobile_image' => 'sponsors/m.png']);

    expect(sponsorshipFormErrors(['sponsorship' => '1'], $files))->toBe([])
        ->and(sponsorshipFormErrors(['sponsorship' => '1'], [], $saved))->toBe([]);
});

test('an in-list advert still needs only one creative, or none', function () {
    expect(sponsorshipFormErrors(['sponsorship' => '0']))->toBe([]);
});

test('the panel is always on a venue\'s listings page, adverts among the listings or not', function () {
    $listings = file_get_contents(resource_path('js/pages/Listings.vue'));

    expect($listings)->toContain('<Advert />')
        ->not->toContain('showAdvert');
});
