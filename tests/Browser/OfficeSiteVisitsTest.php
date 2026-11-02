<?php

use App\Models\SiteVisit;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));

    foreach ([3, 5, 2, 8, 6, 12, 9] as $daysAgo => $visits) {
        SiteVisit::factory()->count($visits)->create(['visited_at' => now()->startOfHour()->subDays($daysAgo)]);
    }

    SiteVisit::insert(array_map(fn (int $visitor): array => [
        'visited_at' => today()->subDays(20)->toDateTimeString(),
        'visitor_hash' => hash('sha256', (string) $visitor),
    ], range(1, 1725)));
});

test('the Office dashboard opens on the last 24 hours and its chart', function () {
    visit('/office')
        ->assertSee('Unique Visitors')
        ->assertSeeIn('.gop-visitors-figure', '3')
        ->assertPresent('svg[role="img"] polyline')
        ->assertNoJavaScriptErrors();
});

test('the tabs switch the figure, shortening it past a thousand', function () {
    visit('/office')
        ->click('7 Days')
        ->assertSeeIn('.gop-visitors-figure', '45')
        ->click('30 Days')
        ->assertSeeIn('.gop-visitors-figure', '1.77k')
        ->assertNoJavaScriptErrors();
});

test('hovering over the chart names the hour and its visitors', function () {
    visit('/office')
        ->assertSee('Unique Visitors')
        ->hover('.gop-visitors-plot')
        ->assertSeeIn('.gop-visitors-tooltip', 'visitor')
        ->assertNoJavaScriptErrors();
});

test('the chart keeps its height on a phone without widening the page', function () {
    $page = visit('/office')->on()->mobile()->assertSee('Unique Visitors');

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue()
        ->and($page->script("document.querySelector('.gop-visitors-plot').getBoundingClientRect().height"))->toBeGreaterThan(100);

    $page->click('30 Days');

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});
