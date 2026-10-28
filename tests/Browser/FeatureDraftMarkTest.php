<?php

use App\Models\Feature;
use App\Models\User;

/*
 * The Office sees a draft where it will appear, ringed with a dashed line
 * and labelled; the published feature beside it is left as it is.
 */
test('the office sees a draft on the home page ringed and labelled', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
    Feature::factory()->create(['title' => 'Out Already', 'created_at' => now()->subDay()]);
    Feature::factory()->draft()->create(['title' => 'Still Writing']);

    visit('/')
        ->assertSee('Still Writing')
        ->assertSee('Out Already')
        ->assertCount('.is-draft', 1)
        ->assertSeeIn('.is-draft', 'Still Writing')
        ->assertScript("getComputedStyle(document.querySelector('.is-draft')).outlineStyle", 'dashed')
        ->assertScript("getComputedStyle(document.querySelector('.is-draft'), '::before').content", '"Draft"')
        ->assertNoJavaScriptErrors();
});

test('the office sees a draft\'s own page ringed and labelled', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
    $draft = Feature::factory()->draft()->create(['title' => 'Still Writing', 'tag_id' => null, 'cover_photo' => null]);

    visit("/magazine/features/{$draft->id}/{$draft->slug}")
        ->assertSeeIn('.is-draft', 'Still Writing')
        ->assertNoJavaScriptErrors();
});
