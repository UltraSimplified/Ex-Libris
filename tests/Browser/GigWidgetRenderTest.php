<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Page;
use App\Models\User;
use App\Models\Venue;
use App\Models\Widget;
use App\Support\Widgets\WidgetDensity;

/**
 * A venue with a few events coming up, one sold out and one running for a
 * week, in a widget of the given style.
 */
function gigWidget(WidgetDensity $density): Widget
{
    $venue = Venue::factory()->create([
        'name' => 'The Trinity Rooms',
        'brand_colour' => '#d4202a',
        'accent_colour1' => '#7a9a8b',
    ]);
    $music = Category::factory()->create(['name' => 'Music']);

    Listing::factory()->for($venue)->for($music)->create([
        'title' => 'Vonda Shepard', 'start_date' => today()->addDay(),
        'event_time_description' => '20:00', 'ticket_price' => '£22', 'event_link' => 'https://tickets.example.com/vonda',
    ]);
    Listing::factory()->for($venue)->for($music)->create([
        'title' => 'Nerina Pallot', 'start_date' => today()->addDays(3),
        'event_time_description' => '20:00', 'sold_out' => true,
    ]);
    Listing::factory()->for($venue)->create([
        'title' => 'Open Studios', 'start_date' => today()->addDays(4), 'end_date' => today()->addDays(10),
    ]);

    return Widget::factory()->for($venue, 'owner')->create(['density' => $density]);
}

test('each style draws its events in the venue\'s colours', function (WidgetDensity $density) {
    $widget = gigWidget($density);

    visit('/embed/'.$widget->key)
        ->assertSee('Vonda Shepard')
        ->assertSee('Nerina Pallot')
        ->assertSee('Open Studios')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'gig-widget-'.$density->value);
})->with(WidgetDensity::cases());

test('the loader puts the widget on a host page and sizes it to its events', function () {
    $widget = gigWidget(WidgetDensity::Compact);

    Page::factory()->create([
        'slug' => 'widget-host',
        'body' => json_encode([
            ['layout' => 'embed', 'key' => 'one', 'attributes' => [
                'code' => '<div data-gop-widget="'.$widget->key.'"></div><script async src="/widget.js"></script>',
            ]],
        ]),
    ]);

    $page = visit('/widget-host')->assertNoJavaScriptErrors();

    $page->wait(1.5);

    expect($page->script('document.querySelectorAll("[data-gop-widget] iframe").length'))->toBe(1);

    $height = $page->script('parseInt(document.querySelector("[data-gop-widget] iframe").style.height, 10)');

    expect($height)->toBeGreaterThan(100)->not->toBe(480);
});

test('the dashboard previews a widget as its settings change', function () {
    $widget = gigWidget(WidgetDensity::Detailed);
    $owner = User::factory()->create();
    $widget->owner->user()->associate($owner)->save();

    $this->actingAs($owner);

    $page = visit('/dashboard/widgets/'.$widget->id.'/edit')
        ->assertSee('Put it on your website')
        ->assertSee('data-gop-widget')
        ->assertNoJavaScriptErrors();

    $page->click('Compact')->wait(1);

    expect($page->script('document.querySelector("iframe[title=\'Widget preview\']").src'))
        ->toContain('density=compact');

    $page->screenshot(filename: 'gig-widget-dashboard');
});

test('saving the dashboard form keeps what is ticked', function () {
    $widget = gigWidget(WidgetDensity::Large);
    $owner = User::factory()->create();
    $widget->owner->user()->associate($owner)->save();

    $this->actingAs($owner);

    visit('/dashboard/widgets/'.$widget->id.'/edit')
        ->click('Save changes')
        ->assertSee('Widget saved')
        ->assertNoJavaScriptErrors();

    $settings = $widget->fresh()->settings();

    expect($settings->showImages)->toBeTrue()
        ->and($settings->showPrice)->toBeTrue();
});
