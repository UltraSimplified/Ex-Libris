<?php

use Laravel\Nova\Nova;

/**
 * Nova inlines the branded logo, and falls back to its own wordmark without
 * a word of complaint when the configured path is wrong, so the failure is
 * silent and easy to miss.
 */
test('the sidebar carries the Good on Paper mark', function () {
    $logo = Nova::logo();

    expect(config('nova.brand.logo'))->toBeFile()
        ->and($logo)->toContain('<svg')
        ->and($logo)->toContain('gop-logo');
});

test('the lockup is set in the brand face', function () {
    $css = file_get_contents(public_path('css/nova-overrides.css'));

    expect($css)->toContain('use.typekit.net')
        ->and($css)->toContain('neue-haas-grotesk-display');
});
