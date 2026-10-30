<?php

use App\Models\Feature;
use App\Models\Issue;

/*
 * Where a page's vertical rule falls: the left edge of the nearest box,
 * from the element the expression finds up, that draws a left border.
 */
function ruleOf(string $element): string
{
    return <<<JS
        (() => {
            let box = {$element};
            while (box && parseFloat(getComputedStyle(box).borderLeftWidth) === 0) {
                box = box.parentElement;
            }

            return Math.round(box.getBoundingClientRect().left);
        })()
        JS;
}

test('on a feature page the footer lines up with the article\'s rule', function () {
    $feature = Feature::factory()->create();

    $page = visit(route('feature', [$feature->id, $feature->slug], absolute: false))->resize(1280, 900);

    expect($page->script(ruleOf("document.querySelector('.site-footer')")))->toBe($page->script(ruleOf("document.querySelector('.feature-body')")));
    $page->assertNoJavaScriptErrors();
});

test('everywhere else the footer keeps the usual rule', function () {
    Issue::factory()->create();

    $page = visit('/')->resize(1280, 900);

    expect($page->script(ruleOf("document.querySelector('.site-footer')")))->toBe($page->script(ruleOf("[...document.querySelectorAll('h1')].find((h) => h.textContent.trim() === 'Features.')")))
        ->and($page->script(ruleOf("document.querySelector('.site-footer')")))->toBeGreaterThan(180);
    $page->assertNoJavaScriptErrors();
});
