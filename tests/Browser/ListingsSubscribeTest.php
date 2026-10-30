<?php

use App\Models\Listing;

/*
 * The subscribe block as it is laid out: whether the cover and the form
 * sit side by side, then the form's background and the email field's.
 */
const SUBSCRIBE_BLOCK = <<<'JS'
    (() => {
        const form = document.getElementById('mc-embedded-subscribe-form');
        const panel = form.closest('.w-80');
        const style = (element, property) => getComputedStyle(element)[property];

        return [
            style(panel.parentElement, 'flexDirection'),
            style(panel, 'backgroundColor'),
            style(form.querySelector('input[type="email"]'), 'backgroundColor'),
        ].join(' | ');
    })()
    JS;

/*
 * Whether the date picker's own stylesheet is in the page yet.
 */
const DATE_PICKER_LOADED = <<<'JS'
    [...document.querySelectorAll('style')].some((style) => style.textContent.includes('.vtd-datepicker-overlay'))
    JS;

/*
 * Waits up to five seconds for the date picker to arrive, and says whether
 * it did.
 */
const DATE_PICKER_ARRIVES = <<<'JS'
    (async () => {
        const arrived = () => [...document.querySelectorAll('style')]
            .some((style) => style.textContent.includes('.vtd-datepicker-overlay'));

        for (let tries = 0; tries < 50 && !arrived(); tries++) {
            await new Promise((resolve) => setTimeout(resolve, 100));
        }

        return arrived();
    })()
    JS;

const DATE_PICKER_CALENDAR = <<<'JS'
    getComputedStyle(document.querySelector('.z-60 .overflow-y-auto > div > div')).backgroundColor
    JS;

const DATE_CHOOSER = <<<'JS'
    (() => {
        const chooser = document.querySelector('.z-60');
        const next = [...chooser.querySelectorAll('button')].find((button) => button.textContent.trim() === 'Next');

        return [
            getComputedStyle(chooser).backgroundColor,
            getComputedStyle(next).backgroundColor,
            getComputedStyle(next).color,
        ].join(' | ');
    })()
    JS;

const LISTING_DATE_SIZE = <<<'JS'
    getComputedStyle(document.querySelector('.divide-silver > div h2')).fontSize
    JS;

test('the subscribe block on the listings page sits beside its cover and goes dark, before and after the date picker loads', function () {
    Listing::factory()->create(['title' => 'Village Fete', 'start_date' => today()->addDay()]);

    visit('/listings')
        ->inDarkMode()
        ->assertScript(DATE_PICKER_LOADED, false)
        ->assertScript(SUBSCRIBE_BLOCK, 'row | rgb(33, 33, 33) | rgba(0, 0, 0, 0)')
        ->click('CHOOSE DATES')
        ->assertScript(DATE_PICKER_ARRIVES, true)
        ->assertScript(DATE_PICKER_CALENDAR, 'rgb(33, 33, 33)')
        ->click('Cancel')
        ->assertScript(SUBSCRIBE_BLOCK, 'row | rgb(33, 33, 33) | rgba(0, 0, 0, 0)')
        ->assertScript(LISTING_DATE_SIZE, '12px')
        ->assertNoJavaScriptErrors();
});

test('in dark mode the date chooser is dark, with a Next button that stands out from it', function () {
    Listing::factory()->create(['title' => 'Village Fete', 'start_date' => today()->addDay()]);

    visit('/listings')
        ->inDarkMode()
        ->click('CHOOSE DATES')
        ->assertScript(DATE_PICKER_ARRIVES, true)
        ->assertScript(DATE_CHOOSER, 'rgb(33, 33, 33) | rgb(252, 237, 60) | rgb(52, 52, 52)')
        ->assertNoJavaScriptErrors();
});

test('in light mode the subscribe block and the date picker stay light', function () {
    Listing::factory()->create(['title' => 'Village Fete', 'start_date' => today()->addDay()]);

    visit('/listings')
        ->assertScript(SUBSCRIBE_BLOCK, 'row | rgb(255, 255, 255) | rgba(0, 0, 0, 0)')
        ->click('CHOOSE DATES')
        ->assertScript(DATE_PICKER_ARRIVES, true)
        ->assertScript(DATE_PICKER_CALENDAR, 'rgb(255, 255, 255)')
        ->assertScript(DATE_CHOOSER, 'rgb(255, 255, 255) | rgb(52, 52, 52) | rgb(255, 255, 255)')
        ->click('Cancel')
        ->assertScript(SUBSCRIBE_BLOCK, 'row | rgb(255, 255, 255) | rgba(0, 0, 0, 0)')
        ->assertNoJavaScriptErrors();
});
