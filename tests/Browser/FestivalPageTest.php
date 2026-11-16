<?php

use App\Models\Festival;
use App\Models\Listing;
use App\Models\Promoter;

/*
 * A festival's page names the promoter putting it on, says when the
 * festival has finished, and says when nothing is in it yet.
 */
test('a finished festival names its promoter and says it has finished over its programme', function () {
    $promoter = Promoter::factory()->create(['name' => 'Northgate Promotions']);
    $festival = Festival::factory()->for($promoter)->create(['name' => 'Riverside Fest 2027', 'about' => 'Three days by the water.']);
    Listing::factory()->create([
        'title' => 'Opening Night',
        'start_date' => today()->subMonth(),
        'promoter_id' => $promoter->id,
        'festival_id' => $festival->id,
    ]);

    visit('/listings/festival/'.$festival->id.'/riverside-fest-2027')
        ->resize(390, 900)
        ->assertSeeIn('[data-test="festival-by"]', 'A festival by Northgate Promotions.')
        ->assertPresent('[data-test="festival-by"] a[href="/listings/promoter/'.$promoter->id.'/northgate-promotions"]')
        ->assertSee('Three days by the water.')
        ->assertSeeIn('[data-test="festival-finished"]', 'RIVERSIDE FEST 2027 HAS FINISHED. THIS WAS THE PROGRAMME.')
        ->assertSee('Opening Night')
        ->assertNoJavaScriptErrors();
});

test('a festival with nothing in it yet says so, and not that it has finished', function () {
    $festival = Festival::factory()->create(['name' => 'Riverside Fest 2028']);

    visit('/listings/festival/'.$festival->id.'/riverside-fest-2028')
        ->assertSee('NOTHING LISTED YET')
        ->assertNotPresent('[data-test="festival-finished"]')
        ->assertNoJavaScriptErrors();
});

test('a promoter\'s page links to its festivals still to come', function () {
    $promoter = Promoter::factory()->create(['name' => 'Northgate Promotions']);
    $festival = Festival::factory()->for($promoter)->create(['name' => 'Riverside Fest 2027']);
    Listing::factory()->create([
        'start_date' => today()->addMonth(),
        'promoter_id' => $promoter->id,
        'festival_id' => $festival->id,
    ]);

    visit('/listings/promoter/'.$promoter->id.'/northgate-promotions')
        ->assertSeeIn('[data-test="promoter-festivals"]', 'Festivals: Riverside Fest 2027')
        ->assertPresent('[data-test="promoter-festivals"] a[href="/listings/festival/'.$festival->id.'/riverside-fest-2027"]')
        ->assertNoJavaScriptErrors();
});
