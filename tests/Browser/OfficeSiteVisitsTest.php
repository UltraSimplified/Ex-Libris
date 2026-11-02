<?php

use App\Models\SiteVisit;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));

    foreach ([3, 5, 2, 8, 6, 12, 9] as $daysAgo => $visits) {
        SiteVisit::factory()->count($visits)->create(['visited_on' => today()->subDays($daysAgo)->toDateString()]);
    }
});

test('the Office dashboard leads with the sparkline and the week\'s total', function () {
    visit('/office')
        ->assertSee('Unique visits')
        ->assertSee('45')
        ->assertPresent('svg[role="img"] polyline')
        ->assertNoJavaScriptErrors();
});

test('the sparkline fits a phone without widening the page', function () {
    $page = visit('/office')->on()->mobile()->assertSee('Unique visits');

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});
