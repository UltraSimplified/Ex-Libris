<?php

use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Models\Widget;
use App\Support\Widgets\WidgetDensity;
use App\Support\Widgets\WidgetSizeMode;

/**
 * Venues and promoters make their own gig widgets from the dashboard. A
 * widget belongs to the venue or promoter, so whoever runs that may change
 * it and nobody else may.
 */
test('a venue owner can make a widget for their venue', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('dashboard.widgets.store'), [
            'owner' => 'venue:'.$venue->id,
            'name' => 'Home page',
            'template' => 'list',
            'density' => 'large',
        ])
        ->assertSessionHasNoErrors();

    $widget = Widget::sole();

    expect($widget->owner->is($venue))->toBeTrue()
        ->and($widget->user_id)->toBe($user->id)
        ->and($widget->density)->toBe(WidgetDensity::Large)
        ->and($widget->active)->toBeTrue()
        ->and($widget->key)->toHaveLength(22)
        ->and($widget->settings()->showImages)->toBeTrue()
        ->and($widget->settings()->showPrice)->toBeTrue()
        ->and($widget->settings()->showVenue)->toBeTrue();
});

test('a promoter can make a widget for their promoter', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('dashboard.widgets.store'), [
            'owner' => 'promoter:'.$promoter->id,
            'name' => 'Tour dates',
            'template' => 'list',
            'density' => 'compact',
        ])
        ->assertRedirect(route('dashboard.widgets.edit', Widget::sole()));

    expect(Widget::sole()->isForPromoter())->toBeTrue();
});

test('nobody can make a widget for a venue they do not run', function () {
    $user = User::factory()->create();
    Venue::factory()->for($user)->create();
    $elsewhere = Venue::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.widgets.store'), [
            'owner' => 'venue:'.$elsewhere->id,
            'name' => 'Not mine',
            'template' => 'list',
            'density' => 'detailed',
        ])
        ->assertForbidden();

    expect(Widget::count())->toBe(0);
});

test('only whoever runs the venue can see, change or delete its widgets', function () {
    $widget = Widget::factory()->create();
    $stranger = User::factory()->create();
    Venue::factory()->for($stranger)->create();

    $this->actingAs($stranger)->get(route('dashboard.widgets.edit', $widget))->assertForbidden();
    $this->actingAs($stranger)->put(route('dashboard.widgets.update', $widget), [
        'name' => 'Taken', 'template' => 'list', 'density' => 'compact',
    ])->assertForbidden();
    $this->actingAs($stranger)->delete(route('dashboard.widgets.destroy', $widget))->assertForbidden();

    expect($widget->fresh())->not->toBeNull();
});

test('a widget passes to whoever is given the venue', function () {
    $venue = Venue::factory()->create();
    $widget = Widget::factory()->for($venue, 'owner')->create();
    $newOwner = User::factory()->create();

    $venue->user()->associate($newOwner)->save();

    $this->actingAs($newOwner)->get(route('dashboard.widgets.edit', $widget))->assertOk();
});

test('the edit page hands over the snippets and the settings', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create(['name' => 'The Prince Albert']);
    $widget = Widget::factory()->for($venue, 'owner')->create();

    $this->actingAs($user)
        ->get(route('dashboard.widgets.edit', $widget))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/widgets/Edit')
            ->where('widget.owner', 'The Prince Albert')
            ->where('widget.use_brand', true)
            ->where('widget.options.count', 12)
            ->where('snippets.script', fn (string $snippet): bool => str_contains($snippet, 'data-gop-widget="'.$widget->key.'"')
                && str_contains($snippet, '/widget.js'))
            ->where('snippets.iframe', fn (string $snippet): bool => str_contains($snippet, '/embed/'.$widget->key))
            ->where('hostReady', false)
        );
});

test('saving settles the options and colours', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create();
    $widget = Widget::factory()->for($venue, 'owner')->create();

    $this->actingAs($user)
        ->put(route('dashboard.widgets.update', $widget), [
            'name' => 'Footer',
            'template' => 'list',
            'density' => 'compact',
            'font' => 'serif',
            'size_mode' => 'fixed',
            'width' => '',
            'height' => '320',
            'active' => '1',
            'options' => ['count' => '5', 'show_images' => '0', 'show_price' => '1', 'show_venue' => '0', 'button_text' => 'Book'],
            'use_brand' => '0',
            'colours' => ['brand_colour' => '#ff0000', 'accent_colour1' => '', 'accent_colour2' => '', 'brand_background_colour' => ''],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard.widgets.edit', $widget));

    $widget->refresh();

    expect($widget->name)->toBe('Footer')
        ->and($widget->size_mode)->toBe(WidgetSizeMode::Fixed)
        ->and($widget->height)->toBe(320)
        ->and($widget->width)->toBeNull()
        ->and($widget->settings()->count)->toBe(5)
        ->and($widget->settings()->showImages)->toBeFalse()
        ->and($widget->settings()->buttonText)->toBe('Book')
        ->and($widget->colours['brand_colour'])->toBe('#ff0000')
        ->and($widget->brand()->brand)->toBe('#ff0000');

    $this->actingAs($user)->put(route('dashboard.widgets.update', $widget), [
        'name' => 'Footer', 'template' => 'list', 'density' => 'compact', 'use_brand' => '1',
    ]);

    expect($widget->fresh()->colours)->toBeNull();
});

test('a fixed-size widget needs a height', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create();
    $widget = Widget::factory()->for($venue, 'owner')->create();

    $this->actingAs($user)
        ->put(route('dashboard.widgets.update', $widget), [
            'name' => 'Footer', 'template' => 'list', 'density' => 'compact', 'size_mode' => 'fixed', 'height' => '',
        ])
        ->assertSessionHasErrors('height');
});

test('the list shows the widgets of what the account runs, narrowed by the sidebar', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create();
    $promoter = Promoter::factory()->for($user)->create();

    Widget::factory()->for($venue, 'owner')->create(['name' => 'Venue widget']);
    Widget::factory()->forPromoter($promoter)->create(['name' => 'Promoter widget']);
    Widget::factory()->create(['name' => 'Someone else']);

    $this->actingAs($user)
        ->get(route('dashboard.widgets.index'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/widgets/Index')
            ->has('widgets', 2)
        );

    $this->actingAs($user)
        ->get(route('dashboard.widgets.index', ['venue' => $venue->id]))
        ->assertInertia(fn ($page) => $page
            ->has('widgets', 1)
            ->where('widgets.0.name', 'Venue widget')
        );
});

test('a venue owner can set their brand colours', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('dashboard.venues.update', $venue), [
            'name' => $venue->name,
            'brand_colour' => '#123456',
            'accent_colour1' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($venue->fresh()->brand_colour)->toBe('#123456')
        ->and($venue->fresh()->accent_colour1)->toBeNull();

    $this->actingAs($user)
        ->put(route('dashboard.venues.update', $venue), ['name' => $venue->name, 'brand_colour' => 'pink'])
        ->assertSessionHasErrors('brand_colour');
});
