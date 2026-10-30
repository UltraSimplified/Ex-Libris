<?php

use App\Support\Brand;

/**
 * Owners pick colours; the brand works out the rest so nothing they pick
 * leaves a widget unreadable.
 */
test('anything unset falls back to Good on Paper\'s own colours', function () {
    $brand = Brand::from([]);

    expect($brand->brand)->toBe(Brand::BRAND)
        ->and($brand->accent)->toBe(Brand::ACCENT)
        ->and($brand->accentTwo)->toBe(Brand::ACCENT)
        ->and($brand->background)->toBe(Brand::BACKGROUND);
});

test('text on each colour is whichever of black or white reads', function () {
    expect(Brand::readableOn('#fced3c'))->toBe('#111111')
        ->and(Brand::readableOn('#212121'))->toBe('#ffffff')
        ->and(Brand::readableOn('#b00020'))->toBe('#ffffff');
});

test('a dark background gets light text', function () {
    $brand = Brand::from(['brand_background_colour' => '#101010']);

    expect($brand->cssVariables()['--gop-text'])->toBe('#f5f5f5');
});

test('something that is not a colour is ignored', function () {
    $brand = Brand::from(['brand_colour' => 'red; background: url(x)', 'accent_colour1' => '#ABC']);

    expect($brand->brand)->toBe(Brand::BRAND)
        ->and($brand->accent)->toBe('#abc');
});
