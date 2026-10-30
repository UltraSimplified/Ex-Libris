<?php

use App\Models\Stockist;

/**
 * A stockist with a website and one without.
 */
function linkedAndUnlinkedStockists(): void
{
    Stockist::forceCreate(['name' => 'Linked Books', 'town' => 'Stroud', 'link' => 'https://example.com']);
    Stockist::forceCreate(['name' => 'Corner Shop', 'town' => 'Stroud', 'link' => null]);
}

/*
 * A stockist name's colour, as "r,g,b", and its contrast against the page
 * behind it, to one decimal place.
 */
function stockistColour(string $name): string
{
    return <<<JS
        (() => {
            const rgb = (colour) => {
                const pen = document.createElement('canvas').getContext('2d');
                pen.fillStyle = colour;
                pen.fillRect(0, 0, 1, 1);

                return [...pen.getImageData(0, 0, 1, 1).data].slice(0, 3);
            };
            const luminance = (channels) => {
                const [r, g, b] = channels.map((value) => {
                    value /= 255;

                    return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
                });

                return 0.2126 * r + 0.7152 * g + 0.0722 * b;
            };

            const name = [...document.querySelectorAll('a, span')].find((element) => element.textContent.trim() === '{$name}');
            let behind = name;
            while (behind && getComputedStyle(behind).backgroundColor === 'rgba(0, 0, 0, 0)') {
                behind = behind.parentElement;
            }

            const text = rgb(getComputedStyle(name).color);
            const page = rgb(behind ? getComputedStyle(behind).backgroundColor : 'white');
            const [lighter, darker] = [luminance(text), luminance(page)].sort((a, b) => b - a);

            return text.join(',') + ' | ' + ((lighter + 0.05) / (darker + 0.05)).toFixed(1);
        })()
        JS;
}

/**
 * @return array{0: string, 1: float}
 */
function colourAndContrast(string $reading): array
{
    [$colour, $contrast] = explode(' | ', $reading);

    return [$colour, (float) $contrast];
}

test('a stockist with a website lightens when pointed at, and stays readable', function (bool $dark) {
    linkedAndUnlinkedStockists();

    $page = visit('/magazine/stockists');

    if ($dark) {
        $page->inDarkMode();
    }

    [$resting] = colourAndContrast($page->script(stockistColour('Linked Books')));

    $page->hover('a[href="https://example.com"]')->wait(0.4);

    [$pointedAt, $contrast] = colourAndContrast($page->script(stockistColour('Linked Books')));

    expect($pointedAt)->not->toBe($resting)
        ->and($contrast)->toBeGreaterThanOrEqual(4.5);

    $page->assertNoJavaScriptErrors();
})->with([
    'light mode' => false,
    'dark mode' => true,
]);

test('the hover grey is lighter in dark mode than in light mode', function () {
    linkedAndUnlinkedStockists();

    $light = visit('/magazine/stockists')->hover('a[href="https://example.com"]')->wait(0.4);
    [$lightGrey] = colourAndContrast($light->script(stockistColour('Linked Books')));

    $dark = visit('/magazine/stockists')->inDarkMode()->hover('a[href="https://example.com"]')->wait(0.4);
    [$darkGrey] = colourAndContrast($dark->script(stockistColour('Linked Books')));

    expect(array_sum(explode(',', $darkGrey)))->toBeGreaterThan(array_sum(explode(',', $lightGrey)));
});

test('a stockist without a website does not react when pointed at', function () {
    linkedAndUnlinkedStockists();

    $page = visit('/magazine/stockists');
    $resting = $page->script(stockistColour('Corner Shop'));

    $page->hover('p.text-3xl:has(> span:first-child)')->wait(0.4);

    expect($page->script(stockistColour('Corner Shop')))->toBe($resting);
});
