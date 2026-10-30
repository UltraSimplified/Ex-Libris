<?php

use App\Models\Advert;
use App\Models\Listing;
use App\Models\Venue;

/*
 * Whether the sponsorship panel is drawn at all at the window's width:
 * "advert" for a sponsorship's creative, "house" for Good on Paper's own,
 * or "none".
 */
const PANEL_SHOWN = <<<'JS'
    (() => {
        const panel = document.querySelector('.sponsorship-panel');
        if (!panel || getComputedStyle(panel).display === 'none') return 'none';
        if (panel.classList.contains('sponsorship-house')) return 'house';
        const shown = [...panel.querySelectorAll('img')].some((image) => getComputedStyle(image).display !== 'none');
        return shown ? 'advert' : 'none';
    })()
    JS;

test('the panel carries a live sponsorship, named for its sponsor, on phones and desktops', function () {
    Advert::factory()->sponsorship()->create(['owner' => 'The Fold', 'title' => 'Natural fabrics']);

    visit('/listings')
        ->assertSee('Sponsored by The Fold')
        ->assertScript(PANEL_SHOWN, 'advert')
        ->resize(400, 900)
        ->assertScript(PANEL_SHOWN, 'advert')
        ->assertNoJavaScriptErrors();
});

test('a sponsorship with no owner is named by its title', function () {
    Advert::factory()->sponsorship()->create(['owner' => null, 'title' => 'Borderless Books']);

    visit('/listings')->assertSee('Sponsored by Borderless Books')->assertNoJavaScriptErrors();
});

test('with no sponsorship live, the panel asks for one rather than going missing', function () {
    Advert::factory()->create(['desktop_image' => 'adverts/d.webp', 'mobile_image' => 'adverts/m.webp']);

    visit('/listings')
        ->assertScript(PANEL_SHOWN, 'house')
        ->assertAttributeContains('.sponsorship-house img', 'src', 'sponsor-good-on-paper')
        ->assertAttributeContains('.sponsorship-house img', 'alt', 'Sponsor Good on Paper')
        ->assertAttribute('.sponsorship-house a', 'href', '/advertise')
        ->assertDontSee('Sponsored by')
        ->assertNoJavaScriptErrors();
});

test('a venue\'s listings page keeps the panel, even with adverts among its listings', function () {
    config(['adverts.listings_every' => 2]);
    Advert::factory()->create(['desktop_image' => 'adverts/d.webp', 'mobile_image' => 'adverts/m.webp']);
    Advert::factory()->sponsorship()->create(['owner' => 'Los Fastidios']);
    $venue = Venue::factory()->create(['name' => 'The Prince Albert']);

    foreach (range(1, 5) as $number) {
        Listing::factory()->create([
            'title' => 'Gig '.$number,
            'venue_id' => $venue->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays($number)->toDateString(),
        ]);
    }

    visit(route('Venue', [$venue->id, Str::slug($venue->name)], absolute: false))
        ->assertCount('.listing-advert', 2)
        ->assertSee('Sponsored by Los Fastidios')
        ->assertScript(PANEL_SHOWN, 'advert')
        ->assertNoJavaScriptErrors();
});
