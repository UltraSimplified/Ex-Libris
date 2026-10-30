<?php

use App\Models\Advert;
use App\Models\AdvertEvent;
use App\Nova\Metrics\ClicksByLocality;
use App\Nova\Metrics\ImpressionsByLocality;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The location partitions sit on an advert's detail page, so each one
 * should count that advert's events and no other advert's.
 */
function localityTotals(string $metric, Advert $advert): array
{
    $request = NovaRequest::create('/nova-api/adverts/metrics');
    $request->merge(['resourceId' => $advert->id]);

    return collect((new $metric)->calculate($request)->jsonSerialize()['value'])
        ->mapWithKeys(fn (array $slice) => [$slice['label'] => $slice['value']])
        ->all();
}

test('impressions by location count only the advert being viewed', function () {
    $advert = Advert::factory()->create();
    $other = Advert::factory()->create();

    AdvertEvent::factory()->for($advert)->create(['locality_bucket' => 'Local']);
    AdvertEvent::factory()->for($other)->count(5)->create(['locality_bucket' => 'Local']);
    AdvertEvent::factory()->for($advert)->create(['type' => 'click', 'locality_bucket' => 'Local']);

    expect(localityTotals(ImpressionsByLocality::class, $advert))->toEqual(['Local' => 1]);
});

test('clicks by location count only the advert being viewed', function () {
    $advert = Advert::factory()->create();
    $other = Advert::factory()->create();

    AdvertEvent::factory()->for($advert)->create(['type' => 'click']);
    AdvertEvent::factory()->for($other)->count(3)->create(['type' => 'click']);
    AdvertEvent::factory()->for($advert)->count(2)->create();

    expect(localityTotals(ClicksByLocality::class, $advert))->toEqual(['Unknown' => 1]);
});
