<?php

beforeEach(function () {
    $this->withoutVite();
});

/**
 * The cookie banner and its scripts sit in the page shell, outside the
 * Inertia app, so they load once and stay put as visitors move about.
 */
test('every page carries the cookie banner and its scripts', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('id="scify-cookies-consent-wrapper"', escape: false)
        ->assertSee('vendor/scify/laravel-cookie-guard/scripts.js', escape: false);
});
