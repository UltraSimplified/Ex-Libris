<?php

use App\Models\Advert;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
});

test('the adverts card turns through every live advert', function () {
    Advert::factory()->create(['title' => 'Stroud Arts Festival', 'sponsorship' => true, 'run_from' => today()->subMonth()]);
    Advert::factory()->create(['title' => 'Los Fastidios', 'sponsorship' => true, 'run_from' => today()->subWeek()]);

    visit('/office')
        ->assertSee('Los Fastidios · Sponsorship panel')
        ->assertSee('1 of 2 live')
        ->click('[aria-label="Next advert"]')
        ->assertSee('Stroud Arts Festival · Sponsorship panel')
        ->assertSee('2 of 2 live')
        ->click('[aria-label="Next advert"]')
        ->assertSee('Los Fastidios · Sponsorship panel')
        ->click('[aria-label="Show Stroud Arts Festival"]')
        ->assertSee('Stroud Arts Festival · Sponsorship panel')
        ->assertNoJavaScriptErrors();
});

test('one live advert sits on its own, with nothing to turn', function () {
    Advert::factory()->create(['title' => 'Stroud Arts Festival', 'sponsorship' => true]);

    visit('/office')
        ->assertSee('Stroud Arts Festival · Sponsorship panel')
        ->assertMissing('[aria-label="Next advert"]')
        ->assertNoJavaScriptErrors();
});

test('with nothing live, the card says so over the figures for every advert', function () {
    Advert::factory()->create(['active' => false]);

    visit('/office')
        ->assertSee('Nothing is running at the moment.')
        ->assertSee('0 impressions')
        ->assertMissing('[aria-label="Next advert"]')
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
