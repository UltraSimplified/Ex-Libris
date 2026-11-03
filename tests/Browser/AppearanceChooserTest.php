<?php

use App\Models\User;

const CHOOSER = '[data-test="appearance-chooser"]';
const SIDEBAR_CHOOSER = '[data-sidebar="footer"] [data-test="appearance-chooser"]';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_promoter' => true]));
});

test('the sidebar has the chooser below the avatar and name, under a line, and nothing at the foot of the page', function () {
    $page = visit('/dashboard')->assertPresent(CHOOSER);

    expect($page->script("document.querySelector('[data-test=\"sidebar-user\"]').closest('ul').nextElementSibling.dataset.sidebar"))->toBe('separator')
        ->and($page->script("!! document.querySelector('[data-test=\"sidebar-user\"]').closest('ul').nextElementSibling.nextElementSibling.matches('".CHOOSER."')"))->toBeTrue()
        ->and($page->script("[...document.querySelectorAll('".CHOOSER."')].filter((found) => found.getClientRects().length > 0).length"))->toBe(1);

    $page->assertNoJavaScriptErrors();
});

test('choosing an appearance applies it, lights its button and survives a reload', function () {
    $page = visit('/dashboard')->assertPresent(CHOOSER);

    $page->click(SIDEBAR_CHOOSER.' [aria-label="Goblin"]');

    expect($page->script('document.documentElement.className'))->toContain('dark')->toContain('goblin')
        ->and($page->script("document.querySelector('".SIDEBAR_CHOOSER." [aria-pressed=\"true\"]').getAttribute('aria-label')"))->toBe('Goblin');

    $page->refresh()->assertPresent(CHOOSER);

    expect($page->script("document.querySelector('".SIDEBAR_CHOOSER." [aria-pressed=\"true\"]').getAttribute('aria-label')"))->toBe('Goblin');

    $page->click(SIDEBAR_CHOOSER.' [aria-label="Light"]');

    expect($page->script('document.documentElement.className'))->not->toContain('dark')->not->toContain('goblin');

    $page->assertNoJavaScriptErrors();
});

test('a collapsed sidebar stands the chooser on end, inside the rail', function () {
    $page = visit('/dashboard')->assertPresent(CHOOSER);

    $page->click('[data-sidebar="trigger"]');
    $page->wait(0.4);

    $chooser = $page->script("(() => { const box = document.querySelector('".SIDEBAR_CHOOSER."').getBoundingClientRect(); const rail = document.querySelector('[data-sidebar=\"sidebar\"]').getBoundingClientRect(); return { tall: box.height > box.width, inside: box.left >= rail.left && box.right <= rail.right }; })()");

    expect($chooser)->toBe(['tall' => true, 'inside' => true]);
});

test('a phone has the chooser at the foot of the page, without widening it', function () {
    $page = visit('/dashboard')->on()->mobile()->assertPresent(CHOOSER);

    expect($page->script("(() => { const found = [...document.querySelectorAll('".CHOOSER."')].filter((one) => one.getClientRects().length > 0); return found.length === 1 && found[0].closest('[data-slot=\"sidebar-inset\"], main') !== null; })()"))->toBeTrue()
        ->and($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->click(CHOOSER.' [aria-label="Dark"]');

    expect($page->script("document.documentElement.classList.contains('dark')"))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});
