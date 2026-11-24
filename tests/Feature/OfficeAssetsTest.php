<?php

use App\Models\User;
use Laravel\Nova\Nova;

/**
 * Nova serves each of the Office's own files at an address made from its
 * name, which carries the file's version so that a deploy that changes the
 * file changes the address, and no browser keeps running an old copy.
 */
test('the office serves the cards script at an address that names its version', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
    $script = collect(Nova::allScripts())->first(fn ($script): bool => $script->path() === public_path('js/office-cards.js'));

    $response = $this->get($script->url());

    expect($script->name())->toMatch('/^office-cards-\d+$/');
    $response->assertOk()->assertSee("app.component('gop-advert-reach'", false);
});

test('the office stylesheet\'s address names its version too', function () {
    $style = collect(Nova::allStyles())->first(fn ($style): bool => $style->path() === public_path('css/nova-overrides.css'));

    expect($style->name())->toMatch('/^admin-overrides-\d+$/');
});
