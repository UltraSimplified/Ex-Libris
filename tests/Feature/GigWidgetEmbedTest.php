<?php

use App\Models\Listing;
use App\Models\Promoter;
use App\Models\Venue;
use App\Models\Widget;
use App\Models\WidgetDailyStat;
use App\Support\Widgets\WidgetDensity;

/**
 * A gig widget, as framed on a venue's or promoter's own site: their
 * upcoming events, served with no session so a busy host page costs no
 * database writes, and frameable by anyone.
 */
test('a venue widget shows its upcoming events, soonest first', function () {
    $venue = Venue::factory()->create();
    $widget = Widget::factory()->for($venue, 'owner')->create();

    Listing::factory()->for($venue)->create(['title' => 'Later Gig', 'start_date' => today()->addDays(9)]);
    Listing::factory()->for($venue)->create(['title' => 'Tonight Gig', 'start_date' => today()]);
    Listing::factory()->for($venue)->create(['title' => 'Finished Gig', 'start_date' => today()->subDays(3)]);
    Listing::factory()->create(['title' => 'Elsewhere Gig', 'start_date' => today()->addDay()]);

    $this->get(route('widgets.embed', $widget->key))
        ->assertOk()
        ->assertSeeInOrder(['Tonight Gig', 'Later Gig'])
        ->assertDontSee('Finished Gig')
        ->assertDontSee('Elsewhere Gig')
        ->assertSee('Listings by');
});

test('a widget starts no session, sets no cookie and may be framed anywhere', function () {
    $widget = Widget::factory()->create();

    $response = $this->get(route('widgets.embed', $widget->key));

    $response->assertOk()
        ->assertHeaderMissing('Set-Cookie')
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeader('Content-Security-Policy', 'frame-ancestors *')
        ->assertHeader('X-Robots-Tag', 'noindex');

    expect($response->headers->get('Cache-Control'))->toContain('public');
});

test('a promoter widget shows their events at every venue, with the venue named', function () {
    $promoter = Promoter::factory()->create();
    $widget = Widget::factory()->forPromoter($promoter)->create();

    $hall = Venue::factory()->create(['name' => 'Subscription Rooms', 'town' => 'Stroud']);
    Listing::factory()->for($promoter)->create(['title' => 'Touring Gig', 'venue_id' => $hall->id]);
    Listing::factory()->create(['title' => 'Somebody Else']);

    $this->get(route('widgets.embed', $widget->key))
        ->assertOk()
        ->assertSee('Touring Gig')
        ->assertSee('Subscription Rooms, Stroud')
        ->assertDontSee('Somebody Else');
});

test('it shows as many events as it is set to, then a link to the rest', function () {
    $venue = Venue::factory()->create();
    $widget = Widget::factory()->for($venue, 'owner')->create(['options' => ['count' => 2]]);
    Listing::factory()->for($venue)->count(3)->sequence(
        ['title' => 'First Gig', 'start_date' => today()->addDay()],
        ['title' => 'Second Gig', 'start_date' => today()->addDays(2)],
        ['title' => 'Third Gig', 'start_date' => today()->addDays(3)],
    )->create();

    $this->get(route('widgets.embed', $widget->key))
        ->assertSee('Second Gig')
        ->assertDontSee('Third Gig')
        ->assertSee('More events');
});

test('every style renders', function (WidgetDensity $density) {
    $venue = Venue::factory()->create();
    $widget = Widget::factory()->for($venue, 'owner')->create(['density' => $density]);
    Listing::factory()->for($venue)->create(['title' => 'Styled Gig', 'event_link' => 'https://tickets.example.com/gig']);

    $this->get(route('widgets.embed', $widget->key))
        ->assertOk()
        ->assertSee('density-'.$density->value)
        ->assertSee('Styled Gig');
})->with(WidgetDensity::cases());

test('a widget follows its owner\'s colours unless it has its own', function () {
    $venue = Venue::factory()->create(['brand_colour' => '#b00020']);
    $following = Widget::factory()->for($venue, 'owner')->create();
    $own = Widget::factory()->for($venue, 'owner')->create(['colours' => ['brand_colour' => '#2a7de1']]);

    $this->get(route('widgets.embed', $following->key))->assertSee('--gop-brand: #b00020', false);
    $this->get(route('widgets.embed', $own->key))->assertSee('--gop-brand: #2a7de1', false);
});

test('changing a listing shows in the widget straight away, cache or not', function () {
    $venue = Venue::factory()->create();
    $widget = Widget::factory()->for($venue, 'owner')->create();
    $listing = Listing::factory()->for($venue)->create(['title' => 'Old Title']);

    $this->get(route('widgets.embed', $widget->key))->assertSee('Old Title');

    $listing->update(['title' => 'New Title']);

    $this->get(route('widgets.embed', $widget->key))
        ->assertSee('New Title')
        ->assertDontSee('Old Title');
});

test('the dashboard can preview unsaved settings without saving them', function () {
    $venue = Venue::factory()->create();
    $widget = Widget::factory()->for($venue, 'owner')->create(['density' => WidgetDensity::Detailed]);

    $response = $this->get(route('widgets.embed', [
        'widget' => $widget->key,
        'preview' => 1,
        'density' => 'compact',
        'colours' => ['brand_colour' => '#00ff00'],
    ]));

    $response->assertOk()
        ->assertSee('density-compact')
        ->assertSee('--gop-brand: #00ff00', false)
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($widget->fresh()->density)->toBe(WidgetDensity::Detailed);
});

test('a switched-off widget leaves an empty frame', function () {
    $venue = Venue::factory()->create();
    $widget = Widget::factory()->for($venue, 'owner')->inactive()->create();
    Listing::factory()->for($venue)->create(['title' => 'Hidden Gig']);

    $this->get(route('widgets.embed', $widget->key))
        ->assertOk()
        ->assertDontSee('Hidden Gig');
});

test('an unknown widget is an empty 404, not a page of the site', function () {
    $this->get('/embed/nothing-here')
        ->assertNotFound()
        ->assertContent('');
});

test('a ticket click is counted and sent to the venue tagged as a referral', function () {
    $venue = Venue::factory()->create();
    $widget = Widget::factory()->for($venue, 'owner')->create();
    $listing = Listing::factory()->for($venue)->create(['event_link' => 'https://tickets.example.com/gig?seat=a']);

    $this->get(route('widgets.click', ['widget' => $widget->key, 'listing' => $listing->id]))
        ->assertRedirect('https://tickets.example.com/gig?seat=a&utm_source=goodonpaper&utm_medium=widget&utm_campaign=widget-'.$widget->id);

    $this->get(route('widgets.click', ['widget' => $widget->key, 'listing' => $listing->id]));

    expect(WidgetDailyStat::where('widget_id', $widget->id)->value('clicks'))->toBe(2);
});

test('a widget cannot send clicks through for somebody else\'s events', function () {
    $widget = Widget::factory()->create();
    $listing = Listing::factory()->create(['event_link' => 'https://tickets.example.com/gig']);

    $this->get(route('widgets.click', ['widget' => $widget->key, 'listing' => $listing->id]))
        ->assertNotFound();
});
