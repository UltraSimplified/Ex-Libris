<?php

use App\Models\User;
use App\Models\Venue;
use App\Models\Widget;

/**
 * On the Office's list of gig widgets, the venue or promoter a widget
 * shows opens the widget itself in a new window, not their record.
 */
test('the owner on the widget list opens the widget in a new window', function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));

    $venue = Venue::factory()->create(['name' => 'The Trinity Rooms']);
    $widget = Widget::factory()->for($venue, 'owner')->create();

    $field = collect($this->getJson('/nova-api/widgets')->assertOk()->json('resources.0.fields'))
        ->firstWhere('name', 'Showing events from');

    expect($field['displayedAs'])
        ->toContain('href="'.route('widgets.embed', ['widget' => $widget->key]).'"')
        ->toContain('target="_blank"')
        ->toContain('The Trinity Rooms');
});
