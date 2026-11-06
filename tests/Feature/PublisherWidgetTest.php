<?php

use App\Models\Listing;
use App\Models\User;
use App\Models\Venue;
use App\Models\Widget;

/**
 * A publisher - a friendly website the office has given the role - makes
 * gig widgets of the events it has bookmarked, without running a venue or
 * a promoter.
 */
test('a publisher can make a widget of their bookmarks', function () {
    $publisher = User::factory()->publisher()->create();

    $this->actingAs($publisher)
        ->post(route('dashboard.widgets.store'), [
            'owner' => 'bookmarks',
            'name' => 'Our picks',
            'template' => 'list',
            'density' => 'detailed',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard.widgets.edit', Widget::sole()));

    $widget = Widget::sole();

    expect($widget->isForBookmarks())->toBeTrue()
        ->and($widget->owner->is($publisher))->toBeTrue();
});

test('an account that is not a publisher cannot make a widget of its bookmarks', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.widgets.store'), [
            'owner' => 'bookmarks',
            'name' => 'Our picks',
            'template' => 'list',
            'density' => 'detailed',
        ])
        ->assertForbidden();

    expect(Widget::count())->toBe(0);
});

test('a publisher is offered their bookmarks when making a widget, and others are not', function () {
    $publisher = User::factory()->publisher()->create();

    $this->actingAs($publisher)
        ->get(route('dashboard.widgets.create', ['bookmarks' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('owner', 'bookmarks')
            ->where('owners.0.value', 'bookmarks')
        );

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.widgets.create'))
        ->assertForbidden();
});

test('only the publisher can see, change or delete their widget', function () {
    $widget = Widget::factory()->forBookmarks()->create();
    $otherPublisher = User::factory()->publisher()->create();

    $this->actingAs($otherPublisher)->get(route('dashboard.widgets.edit', $widget))->assertForbidden();
    $this->actingAs($otherPublisher)->delete(route('dashboard.widgets.destroy', $widget))->assertForbidden();

    $this->actingAs($widget->owner)
        ->get(route('dashboard.widgets.edit', $widget))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('widget.for_bookmarks', true)
            ->where('widget.spans_venues', true)
            ->where('widget.owner', 'My bookmarks')
            ->where('ownerColours', null)
        );
});

test('the list narrowed to bookmarks shows only the publisher\'s own widgets', function () {
    $publisher = User::factory()->publisher()->create();
    $mine = Widget::factory()->forBookmarks($publisher)->create(['name' => 'Our picks']);
    Widget::factory()->forBookmarks()->create(['name' => 'Somebody else']);
    $venue = Venue::factory()->for($publisher)->create();
    Widget::factory()->for($venue, 'owner')->create(['name' => 'Venue widget']);

    $this->actingAs($publisher)
        ->get(route('dashboard.widgets.index', ['bookmarks' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('bookmarks', true)
            ->has('widgets', 1)
            ->where('widgets.0.id', $mine->id)
        );

    $this->actingAs($publisher)
        ->get(route('dashboard.widgets.index'))
        ->assertInertia(fn ($page) => $page->has('widgets', 2));
});

test('a publisher widget shows their upcoming bookmarks at every venue, with the venue named', function () {
    $publisher = User::factory()->publisher()->create(['name' => 'TEST ENTRY Publisher']);
    $widget = Widget::factory()->forBookmarks($publisher)->create();

    $hall = Venue::factory()->create(['name' => 'Subscription Rooms', 'town' => 'Stroud']);
    $picked = Listing::factory()->create(['title' => 'Picked Gig', 'venue_id' => $hall->id, 'start_date' => today()->addDay()]);
    $finished = Listing::factory()->create(['title' => 'Finished Gig', 'start_date' => today()->subDays(3)]);
    Listing::factory()->create(['title' => 'Unpicked Gig', 'start_date' => today()->addDay()]);
    $publisher->bookmarks()->attach([$picked->id, $finished->id]);

    $this->get(route('widgets.embed', $widget->key))
        ->assertOk()
        ->assertSee('Picked Gig')
        ->assertSee('Subscription Rooms, Stroud')
        ->assertDontSee('Finished Gig')
        ->assertDontSee('Unpicked Gig')
        ->assertDontSee('TEST ENTRY Publisher');
});

test('a publisher widget sends clicks through only for events they have bookmarked', function () {
    $publisher = User::factory()->publisher()->create();
    $widget = Widget::factory()->forBookmarks($publisher)->create();
    $picked = Listing::factory()->create(['event_link' => 'https://tickets.example.com/picked']);
    $unpicked = Listing::factory()->create(['event_link' => 'https://tickets.example.com/unpicked']);
    $publisher->bookmarks()->attach($picked->id);

    $this->get(route('widgets.click', ['widget' => $widget->key, 'listing' => $picked->id]))
        ->assertRedirect();

    $this->get(route('widgets.click', ['widget' => $widget->key, 'listing' => $unpicked->id]))
        ->assertNotFound();
});
