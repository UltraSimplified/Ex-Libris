<?php

use App\Models\Feature;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
});

/*
 * The eye on a features row opens the feature as the public will read it,
 * framed over the list, as it does for listings, rather than Nova's detail
 * page; Close shuts it and leaves the list where it was.
 */
test('the eye on a features row opens the feature in a preview over the list', function () {
    $feature = Feature::factory()->create(['subject' => 'Crappy Living', 'title' => 'Crappy Living: Our Lady of the In Between']);

    visit('/office/resources/features')
        ->assertSee('Crappy Living')
        ->click("a[dusk='{$feature->id}-view-button']")
        ->assertPresent('.gop-preview-overlay')
        ->assertAttribute('.gop-preview-frame', 'src', '/magazine/features/'.$feature->id.'/preview')
        ->assertPathIs('/office/resources/features')
        ->wait(2)
        ->assertScript(<<<'JS'
            document.querySelector('.gop-preview-frame').contentDocument.body.innerText.includes('Crappy Living: Our Lady of the In Between')
            JS, true)
        ->click('Close')
        ->assertMissing('.gop-preview-overlay')
        ->assertNoJavaScriptErrors();
});
