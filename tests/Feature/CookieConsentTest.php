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

/**
 * Its styles come in app.css, and its script waits for the page, so the
 * banner holds up nothing the visitor came to see.
 */
test('the cookie banner does not hold up the first paint', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<script defer src="'.asset('vendor/scify/laravel-cookie-guard/scripts.js'), escape: false)
        ->assertDontSee('vendor/scify/laravel-cookie-guard/styles.css', escape: false)
        ->assertDontSee('vendor/scify/laravel-cookie-guard/_variables.css', escape: false);
});
