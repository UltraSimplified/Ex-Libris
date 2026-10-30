<?php

use App\Models\Advert;
use App\Models\Feature;
use App\Models\Issue;

/**
 * The colour of the "Sponsored by" label over the sponsorship panel, with
 * dark mode on or off.
 */
function advertLabelColour(bool $dark): string
{
    $toggle = $dark ? 'true' : 'false';

    return <<<JS
        (() => {
            document.documentElement.classList.toggle('dark', {$toggle});
            const label = [...document.querySelectorAll('.sponsorship-panel p')].find((p) => p.textContent.trim().startsWith('Sponsored by'));

            return getComputedStyle(label).color;
        })()
        JS;
}

/**
 * The panel behind the label stays pale grey in dark mode, but the label
 * took dark mode's pale text with it and all but vanished.
 */
test('the sponsorship label stays dark on its pale panel in either mode', function (string $path) {
    Issue::factory()->create();
    Advert::factory()->sponsorship()->create();
    $feature = Feature::factory()->create();

    $page = visit(str_replace('{feature}', route('feature', [$feature->id, $feature->slug], absolute: false), $path));

    expect($page->script(advertLabelColour(dark: false)))->toBe('rgb(52, 52, 52)')
        ->and($page->script(advertLabelColour(dark: true)))->toBe('rgb(52, 52, 52)');
})->with([
    'home page' => ['/'],
    'feature page' => ['{feature}'],
]);
