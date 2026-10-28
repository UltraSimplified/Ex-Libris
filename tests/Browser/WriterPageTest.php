<?php

use App\Models\Feature;
use App\Models\Issue;
use App\Models\Page;
use App\Models\User;

test('a writer\'s page lists their articles and turns to the writers either side', function () {
    $harper = User::factory()->writer()->create(['name' => 'Paul Harper']);
    User::factory()->writer()->create(['name' => 'Adam Horovitz']);
    User::factory()->writer()->create(['name' => 'Judith Gunn']);
    Feature::factory()->for($harper)->create(['title' => 'Ishmael Ensemble']);

    $page = visit('/our-writers/paul-harper')
        ->assertSee('Paul Harper')
        ->assertSee('Ishmael Ensemble')
        ->assertSeeIn('[rel="prev"]', 'Judith Gunn')
        ->assertSeeIn('[rel="prev"]', 'PREVIOUS WRITER')
        ->assertSeeIn('[rel="next"]', 'Adam Horovitz')
        ->assertSeeIn('[rel="next"]', 'NEXT WRITER');

    // Large, rounded well past a button's corners, and as wide as what each
    // says rather than half the panel - with 40px above and 60px below.
    expect($page->script("parseFloat(getComputedStyle(document.querySelector('[rel=next]')).borderTopLeftRadius)"))->toBeGreaterThan(30)
        ->and($page->script("document.querySelector('[rel=next]').getBoundingClientRect().height"))->toBeGreaterThanOrEqual(112)
        ->and($page->script("document.querySelector('[rel=next]').getBoundingClientRect().width < document.querySelector('.writer-nav').clientWidth / 2 - 40"))->toBeTrue()
        ->and($page->script("Math.round(document.querySelector('[rel=next]').getBoundingClientRect().right) === Math.round(document.querySelector('.writer-nav').getBoundingClientRect().right - parseFloat(getComputedStyle(document.querySelector('.writer-nav')).paddingRight))"))->toBeTrue()
        ->and($page->script("getComputedStyle(document.querySelector('.writer-nav')).paddingTop"))->toBe('40px')
        ->and($page->script("getComputedStyle(document.querySelector('.writer-nav')).paddingBottom"))->toBe('60px');

    $page->click('[rel="next"]')
        ->assertPathIs('/our-writers/adam-horovitz')
        ->assertSeeIn('.writer-name', 'Adam Horovitz')
        ->assertSeeIn('[rel="prev"]', 'Paul Harper')
        ->assertNoJavaScriptErrors();
});

test('a writer\'s page leads back to all the writers', function () {
    User::factory()->writer()->create(['name' => 'Paul Harper']);
    Page::factory()->create(['title' => 'Our Writers', 'slug' => 'our-writers']);

    visit('/our-writers/paul-harper')
        ->assertSeeIn('.all-writers', 'back')
        ->click('.all-writers')
        ->assertPathBeginsWith('/our-writers')
        ->assertPathIsNot('/our-writers/paul-harper')
        ->assertSee('PAUL HARPER')
        ->assertNoJavaScriptErrors();
});

/**
 * The issue on sale now is shown first, as a cover with no link.
 */
test('a writer\'s back issues are covers that download the issue', function () {
    $writer = User::factory()->writer()->create(['name' => 'John Seagrave']);
    $issues = collect([53, 63, 70])->mapWithKeys(fn (int $number): array => [$number => Issue::factory()->create(['issue_number' => $number])]);
    $writer->issues()->attach($issues->pluck('id'));

    visit('/our-writers/john-seagrave')
        ->assertSee('In print.')
        ->assertCount('.writer-issues li', 3)
        ->assertPresent('.writer-issues a[href="/download/'.$issues[63]->id.'"] img')
        ->assertPresent('.writer-issues a[href="/download/'.$issues[53]->id.'"] img')
        ->assertMissing('.writer-issues a[href="/download/'.$issues[70]->id.'"]')
        ->assertSeeIn('.writer-issues li:first-child', 'ISSUE 70')
        ->assertMissing('.writer-issues li:first-child a')
        ->assertNoJavaScriptErrors();
});

/**
 * Where two lozenges sit on the row: whether side by side, whether both
 * inside the panel, and the size they were drawn at.
 */
function lozengeRow(): string
{
    return <<<'JS'
        (() => {
            const nav = document.querySelector('.writer-nav').getBoundingClientRect();
            const prev = document.querySelector('[rel=prev]');
            const next = document.querySelector('[rel=next]');
            const a = prev.getBoundingClientRect();
            const b = next.getBoundingClientRect();

            return {
                sideBySide: Math.abs(a.top - b.top) < 2 && a.right <= b.left,
                inside: a.left >= nav.left && b.right <= nav.right + 1,
                size: prev.dataset.size,
            };
        })()
        JS;
}

/**
 * Long names either side, which do not fit at full size on anything but a
 * wide screen: the lozenges are drawn smaller until they share the row.
 */
test('the writers either side are drawn smaller until they fit side by side', function (int $width) {
    User::factory()->writer()->create(['name' => 'Alexandra Constantinople-Smythe']);
    User::factory()->writer()->create(['name' => 'Bartholomew Fitzwilliam-Hargreaves']);
    User::factory()->writer()->create(['name' => 'Cy Bo']);

    $page = visit('/our-writers/cy-bo')->resize($width, 900);
    $page->wait(0.5);

    expect($page->script(lozengeRow()))->toMatchArray(['sideBySide' => true, 'inside' => true]);
    $page->assertNoJavaScriptErrors();
})->with([
    'wide screen' => [1920],
    'laptop' => [1024],
    'phone' => [375],
]);

test('short names on a wide screen keep the full size', function () {
    User::factory()->writer()->create(['name' => 'Paul Harper']);
    User::factory()->writer()->create(['name' => 'Adam Horovitz']);
    User::factory()->writer()->create(['name' => 'Judith Gunn']);

    $page = visit('/our-writers/paul-harper')->resize(1600, 900);
    $page->wait(0.5);

    expect($page->script(lozengeRow()))->toBe(['sideBySide' => true, 'inside' => true, 'size' => 'lg']);
});

test('on a phone the lozenges take their smallest size', function () {
    User::factory()->writer()->create(['name' => 'Paul Harper']);
    User::factory()->writer()->create(['name' => 'Adam Horovitz']);
    User::factory()->writer()->create(['name' => 'Judith Gunn']);

    $page = visit('/our-writers/paul-harper')->resize(375, 800);
    $page->wait(0.5);

    expect($page->script(lozengeRow()))->toBe(['sideBySide' => true, 'inside' => true, 'size' => 'sm']);
});
