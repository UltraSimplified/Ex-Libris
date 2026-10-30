<?php

use Symfony\Component\Finder\Finder;

/**
 * The magazine sets nothing in italics, bylines included. Nothing in the
 * templates asks for a slant, and what a browser or a feature's own text
 * would slant is drawn upright.
 */
test('no template asks for italics', function () {
    $files = Finder::create()->files()->in([resource_path('js'), resource_path('views')])
        ->name(['*.vue', '*.blade.php', '*.ts']);

    $slanted = collect($files)
        ->filter(fn ($file): bool => (bool) preg_match('/(?<!not-)\bitalic\b|font-style:\s*italic/', $file->getContents()))
        ->map(fn ($file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($slanted)->toBe([]);
});

test('emphasis is drawn upright', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toMatch('/em,\s*i,\s*cite,\s*address,\s*dfn,\s*var\s*\{\s*font-style: normal;/');
});
