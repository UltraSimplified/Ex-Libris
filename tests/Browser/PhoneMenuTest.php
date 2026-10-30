<?php

/*
 * How each phone panel is drawn: "parked" when it is out of the page
 * altogether, "in" when it is on screen, "out" when it waits off the edge.
 * The yellow main menu first, then the black submenu.
 */
const PHONE_PANELS = <<<'JS'
    ['ul.bg-hivis', 'ul.bg-black'].map((selector) => {
        const panel = document.querySelector(selector);
        if (getComputedStyle(panel).display === 'none') return 'parked';
        return Math.round(panel.getBoundingClientRect().left) === 0 ? 'in' : 'out';
    }).join(' ')
    JS;

/*
 * Safari on iOS 26 tints its toolbars from fixed elements at the screen's
 * edges, so a shut panel waiting just off-screen left the bars yellow. A
 * shut panel is taken out of the page instead.
 */
test('on a phone the menu is out of the page until it is opened, and again once it has slid away', function () {
    visit('/listings')
        ->resize(390, 844)
        ->assertScript(PHONE_PANELS, 'parked parked')
        ->click('[aria-label="Toggle menu"]')
        ->wait(0.7)
        ->assertScript(PHONE_PANELS, 'in parked')
        ->click('[aria-label="Toggle menu"]')
        ->wait(0.7)
        ->assertScript(PHONE_PANELS, 'parked parked')
        ->assertNoJavaScriptErrors();
});

/*
 * Once the Magazine panel has slid over the menu, the menu is taken out of
 * the page behind it, so the black panel is the only one at the edges for
 * Safari to tint its bars from.
 */
test('the Magazine panel slides in over the menu, which is taken out behind it, and both go when shut', function () {
    visit('/listings')
        ->resize(390, 844)
        ->click('[aria-label="Toggle menu"]')
        ->wait(0.7)
        ->click('ul.bg-hivis span.md\\:hidden:text-is("Magazine.")')
        ->wait(0.7)
        ->assertScript(PHONE_PANELS, 'parked in')
        ->assertSee('Who we are.')
        ->click('[aria-label="Toggle menu"]')
        ->wait(0.7)
        ->assertScript(PHONE_PANELS, 'parked parked')
        ->assertNoJavaScriptErrors();
});

test('on desktop the menu and its second tier are part of the header as before', function () {
    visit('/magazine/features')
        ->resize(1280, 900)
        ->assertScript(<<<'JS'
            ['ul.bg-hivis', 'ul.bg-black'].map((selector) => getComputedStyle(document.querySelector(selector)).display).join(' ')
            JS, 'flex flex')
        ->assertNoJavaScriptErrors();
});
