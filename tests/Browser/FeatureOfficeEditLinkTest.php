<?php

use App\Models\Feature;
use App\Models\User;

/*
 * The Office's pencil beside a feature's category, as listings have beside
 * their heart, opens it in the Office's editor. Only the office's own
 * accounts are shown it.
 */

/** The pencil, by what it says to a screen reader. */
const FEATURE_PENCIL = 'a[aria-label="Edit this feature in the Office"]';

test('the office has a pencil to edit a feature from its own page', function () {
    $this->actingAs(User::factory()->create(['email' => 'alex@goodonpaper.info']));
    $feature = Feature::factory()->create(['title' => 'Snapped Ankles at the Golden Lion']);

    visit("/magazine/features/{$feature->id}/{$feature->slug}")
        ->assertSee('Snapped Ankles at the Golden Lion')
        ->assertAttribute(FEATURE_PENCIL, 'href', "/office/resources/features/{$feature->id}/edit")
        ->assertNoJavaScriptErrors();
});

test('the office has a pencil to edit each feature on the features page', function () {
    $this->actingAs(User::factory()->create(['email' => 'alex@goodonpaper.info']));
    $older = Feature::factory()->create(['created_at' => now()->subDays(2)]);
    $newer = Feature::factory()->create(['created_at' => now()->subDay()]);

    visit('/magazine/features')
        ->assertPresent(FEATURE_PENCIL.'[href="/office/resources/features/'.$older->id.'/edit"]')
        ->assertPresent(FEATURE_PENCIL.'[href="/office/resources/features/'.$newer->id.'/edit"]')
        ->assertNoJavaScriptErrors();
});

test('nobody outside the office is shown the pencil', function () {
    $feature = Feature::factory()->create(['title' => 'Snapped Ankles at the Golden Lion']);

    visit("/magazine/features/{$feature->id}/{$feature->slug}")
        ->assertSee('Snapped Ankles at the Golden Lion')
        ->assertNotPresent(FEATURE_PENCIL)
        ->assertNoJavaScriptErrors();
});

/*
 * In the Office's own preview the pencil would open the editor inside the
 * frame it is shown in.
 */
test('the office preview of a feature has no pencil', function () {
    $this->actingAs(User::factory()->create(['email' => 'alex@goodonpaper.info']));
    $feature = Feature::factory()->create(['title' => 'Snapped Ankles at the Golden Lion']);

    visit("/magazine/features/{$feature->id}/preview")
        ->assertSee('Snapped Ankles at the Golden Lion')
        ->assertNotPresent(FEATURE_PENCIL)
        ->assertNoJavaScriptErrors();
});
