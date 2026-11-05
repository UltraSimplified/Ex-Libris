<?php

use App\Models\SiteVisit;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));

    foreach ([3, 5, 2, 8, 6, 12, 9] as $daysAgo => $visits) {
        SiteVisit::factory()->count($visits)->lasting(252)->create([
            'visited_at' => now()->startOfHour()->subDays($daysAgo),
            'requests' => 4,
        ]);
    }

    SiteVisit::insert(array_map(fn (int $visitor): array => [
        'visited_at' => today()->subDays(20)->toDateTimeString(),
        'visitor_hash' => hash('sha256', (string) $visitor),
    ], range(1, 1725)));
});

test('the Office dashboard opens on the last 24 hours and both its lines', function () {
    visit('/office')
        ->assertSee('Unique Visitors')
        ->assertSee('Page Views')
        ->assertSee('Average Visit')
        ->assertSeeIn('.gop-visitors-unique', '3')
        ->assertSeeIn('.gop-visitors-views', '12')
        ->assertPresent('svg[role="img"] polyline.gop-visitors-line')
        ->assertPresent('svg[role="img"] polyline.gop-visitors-requests')
        ->assertNoJavaScriptErrors();
});

test('the tabs switch both figures, shortening them past a thousand', function () {
    visit('/office')
        ->click('7 Days')
        ->assertSeeIn('.gop-visitors-unique', '45')
        ->assertSeeIn('.gop-visitors-views', '180')
        ->click('30 Days')
        ->assertSeeIn('.gop-visitors-unique', '1.77k')
        ->assertSeeIn('.gop-visitors-views', '1.91k')
        ->assertNoJavaScriptErrors();
});

test('hovering over the chart names the hour, its visitors and its page views', function () {
    visit('/office')
        ->assertSee('Unique Visitors')
        ->hover('.gop-visitors-plot')
        ->assertSeeIn('.gop-visitors-tooltip', 'visitor')
        ->assertSeeIn('.gop-visitors-tooltip', 'page view')
        ->assertNoJavaScriptErrors();
});

test('the average visit reads as minutes and seconds, and as a dash with nothing to time', function () {
    visit('/office')->assertSeeIn('.gop-visitors-visit', '4m 12s');

    SiteVisit::query()->update(['first_seen_at' => null, 'last_seen_at' => null]);

    visit('/office')
        ->assertSeeIn('.gop-visitors-visit', '—')
        ->assertNoJavaScriptErrors();
});

test('the page views line is drawn in hot pink', function () {
    $page = visit('/office')->assertSee('Page Views');

    expect($page->script("getComputedStyle(document.querySelector('polyline.gop-visitors-requests')).stroke"))
        ->toBe('rgb(236, 72, 153)');

    $page->assertNoJavaScriptErrors();
});

/*
 * The two shadings must not sit over each other: blue fills from the axis up
 * to the visitors line, and pink rests on that line rather than starting from
 * the axis as well, which would show through as a muddled third colour.
 */
test('the page views shading is a band resting on the unique visitors line', function () {
    foreach (range(0, 23) as $hoursAgo) {
        SiteVisit::factory()->count(2)->create(['visited_at' => now()->startOfHour()->subHours($hoursAgo)]);
    }

    SiteVisit::query()->update(['requests' => 4]);

    $page = visit('/office')->assertSee('Page Views');

    $bottomOf = fn (string $selector): float => (float) $page->script(
        "(() => { const box = document.querySelector('{$selector}').getBBox(); return box.y + box.height })()"
    );

    expect($bottomOf('polygon.gop-visitors-area'))->toBe(100.0)
        ->and($bottomOf('polygon.gop-visitors-band'))
        ->toBeLessThan(100.0)
        ->toBe($bottomOf('polyline.gop-visitors-line:not(.gop-visitors-requests)'));

    $page->assertNoJavaScriptErrors();
});

test('the chart keeps its height on a phone without widening the page', function () {
    $page = visit('/office')->on()->mobile()->assertSee('Unique Visitors');

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue()
        ->and($page->script("document.querySelector('.gop-visitors-plot').getBoundingClientRect().height"))->toBeGreaterThan(100);

    $page->click('30 Days');

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});
