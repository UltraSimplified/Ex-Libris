<?php

use App\Models\Advert;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
});

test('the in-list section turns through every live in-list advert while the sponsorship stays put', function () {
    Advert::factory()->create(['title' => 'Stroud Arts Festival', 'run_from' => today()->subMonth()]);
    Advert::factory()->create(['title' => 'Los Fastidios', 'run_from' => today()->subWeek()]);
    Advert::factory()->create(['title' => 'Good on Paper', 'sponsorship' => true]);

    visit('/office')
        ->assertSeeIn('[aria-label="In-list"]', 'Los Fastidios')
        ->assertSeeIn('[aria-label="In-list"]', '1 of 2 live')
        ->click('[aria-label="Next advert"]')
        ->assertSeeIn('[aria-label="In-list"]', 'Stroud Arts Festival')
        ->assertSeeIn('[aria-label="In-list"]', '2 of 2 live')
        ->click('[aria-label="Next advert"]')
        ->assertSeeIn('[aria-label="In-list"]', 'Los Fastidios')
        ->click('[aria-label="Show Stroud Arts Festival"]')
        ->assertSeeIn('[aria-label="In-list"]', 'Stroud Arts Festival')
        ->assertSeeIn('[aria-label="Sponsorship panel"]', 'Good on Paper')
        ->assertNoJavaScriptErrors();
});

test('the sponsorship panel section shows one sponsorship, with its report and nothing to turn', function () {
    Advert::factory()->create(['title' => 'Good on Paper', 'sponsorship' => true, 'run_from' => today()->subMonth()]);
    $newer = Advert::factory()->create(['title' => 'Stroud Arts Festival', 'sponsorship' => true, 'run_from' => today()->subWeek()]);

    visit('/office')
        ->assertSeeIn('[aria-label="Sponsorship panel"]', 'Stroud Arts Festival')
        ->assertSeeIn('[aria-label="Sponsorship panel"]', '2 sponsorships are live and taking turns in the panel.')
        ->assertMissing('[aria-label="Next advert"]')
        ->assertAttributeContains('[aria-label="Sponsorship panel"] .gop-report-button', 'href', "/exports/adverts/{$newer->id}/report")
        ->assertNoJavaScriptErrors();
});

test('one live advert sits on its own, with nothing to turn', function () {
    Advert::factory()->create(['title' => 'Stroud Arts Festival']);

    visit('/office')
        ->assertSeeIn('[aria-label="In-list"]', 'Stroud Arts Festival')
        ->assertMissing('[aria-label="Next advert"]')
        ->assertNoJavaScriptErrors();
});

test('with nothing live, each section says so over the figures for its slot', function () {
    Advert::factory()->create(['active' => false]);

    visit('/office')
        ->assertSeeIn('[aria-label="In-list"]', 'No in-list advert is running at the moment.')
        ->assertSeeIn('[aria-label="In-list"]', '0 impressions')
        ->assertSeeIn('[aria-label="Sponsorship panel"]', 'No sponsorship is running, so the panel shows Good on Paper’s own advert.')
        ->assertSeeIn('[aria-label="Sponsorship panel"]', '0 impressions')
        ->assertMissing('.gop-report-button')
        ->assertNoJavaScriptErrors();
});

test('the adverts card offers the report of the advert on show, to the right of the controls', function () {
    $older = Advert::factory()->create(['title' => 'Stroud Arts Festival', 'run_from' => today()->subMonth()]);
    $newer = Advert::factory()->create(['title' => 'Los Fastidios', 'run_from' => today()->subWeek()]);

    visit('/office')
        ->assertSee('Download report')
        ->assertAttributeContains('.gop-report-button', 'href', "/exports/adverts/{$newer->id}/report")
        ->assertAttributeContains('.gop-report-button', 'download', 'good-on-paper-advert-report-los-fastidios-')
        ->assertScript(<<<'JS'
            (() => {
                const button = document.querySelector('.gop-report-button').getBoundingClientRect();
                const next = document.querySelector('[aria-label="Next advert"]').getBoundingClientRect();
                const row = document.querySelector('.gop-carousel-controls').getBoundingClientRect();

                return button.left > next.right && Math.abs(button.right - row.right) < 2;
            })()
            JS)
        ->click('[aria-label="Next advert"]')
        ->assertAttributeContains('.gop-report-button', 'href', "/exports/adverts/{$older->id}/report")
        ->assertNoJavaScriptErrors();
});

test('one live advert still offers its report', function () {
    $advert = Advert::factory()->create(['title' => 'Stroud Arts Festival']);

    visit('/office')
        ->assertMissing('[aria-label="Next advert"]')
        ->assertAttributeContains('.gop-report-button', 'href', "/exports/adverts/{$advert->id}/report")
        ->assertNoJavaScriptErrors();
});

test('an advert\'s page in the Office offers its report', function () {
    $advert = Advert::factory()->create(['title' => 'Los Fastidios']);

    visit("/office/resources/adverts/{$advert->id}")
        ->assertSee('Download report')
        ->assertAttributeContains('.gop-report-button', 'href', "/exports/adverts/{$advert->id}/report")
        ->assertAttributeContains('.gop-report-button', 'download', 'good-on-paper-advert-report-los-fastidios-')
        ->assertNoJavaScriptErrors();
});
