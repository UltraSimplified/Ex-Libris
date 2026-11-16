<?php

use App\Models\Festival;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Models\Widget;

/**
 * A festival has gig widgets as a promoter does, made by an account that
 * runs it: its events, in its colours, crediting its page, and once it has
 * all been, the whole programme rather than nothing.
 */
function festivalAccount(): array
{
    $account = User::factory()->runsFestivals()->create();
    $festival = Festival::factory()
        ->for(Promoter::factory()->for($account))
        ->create(['name' => 'Riverside Fest 2027', 'brand_colour' => '#ff69b4']);

    return [$account, $festival];
}

test('a festival runner makes a widget for its festival, offered by name', function () {
    [$account, $festival] = festivalAccount();

    $this->actingAs($account)
        ->get(route('dashboard.widgets.create', ['festival' => $festival->id]))
        ->assertInertia(fn ($page) => $page
            ->where('owner', 'festival:'.$festival->id)
            ->where('owners', fn ($owners): bool => collect($owners)->contains([
                'value' => 'festival:'.$festival->id,
                'label' => 'Riverside Fest 2027 (festival)',
            ])));

    $this->actingAs($account)
        ->post(route('dashboard.widgets.store'), [
            'owner' => 'festival:'.$festival->id,
            'name' => 'Programme',
            'template' => 'list',
            'density' => 'compact',
        ])
        ->assertRedirect(route('dashboard.widgets.edit', Widget::sole()));

    expect(Widget::sole()->isForFestival())->toBeTrue()
        ->and(Widget::sole()->spansVenues())->toBeTrue();
});

test('the edit page sends a festival widget back to the festival for its colours', function () {
    [$account, $festival] = festivalAccount();
    $widget = Widget::factory()->forFestival($festival)->create();

    $this->actingAs($account)
        ->get(route('dashboard.widgets.edit', $widget))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('widget.for_festival', true)
            ->where('widget.owner', 'Riverside Fest 2027')
            ->where('ownerColours.brand_colour', '#ff69b4')
            ->where('ownerEditUrl', route('dashboard.festivals.edit', $festival)));
});

test('the widget list narrows to a festival the account runs', function () {
    [$account, $festival] = festivalAccount();
    $mine = Widget::factory()->forFestival($festival)->create(['name' => 'Programme']);
    Widget::factory()->forPromoter($festival->promoter)->create(['name' => 'Tour dates']);

    $this->actingAs($account)
        ->get(route('dashboard.widgets.index', ['festival' => $festival->id]))
        ->assertInertia(fn ($page) => $page
            ->has('widgets', 1)
            ->where('widgets.0.id', $mine->id)
            ->where('festival.name', 'Riverside Fest 2027'));

    $this->actingAs($account)
        ->get(route('dashboard.widgets.index'))
        ->assertInertia(fn ($page) => $page->has('widgets', 2));
});

test('nobody makes a widget for a festival they do not run', function () {
    [$account] = festivalAccount();
    $elsewhere = Festival::factory()->create();

    $this->actingAs($account)
        ->post(route('dashboard.widgets.store'), [
            'owner' => 'festival:'.$elsewhere->id,
            'name' => 'Theirs',
            'template' => 'list',
            'density' => 'compact',
        ])
        ->assertForbidden();

    expect(Widget::count())->toBe(0);
});

test('an account without Runs Festivals is offered no festival and refused one', function () {
    $account = User::factory()->create();
    $festival = Festival::factory()->for(Promoter::factory()->for($account))->create();

    $this->actingAs($account)
        ->get(route('dashboard.widgets.create'))
        ->assertInertia(fn ($page) => $page->where('owners', fn ($owners): bool => collect($owners)
            ->every(fn (array $owner): bool => ! str_starts_with($owner['value'], 'festival:'))));

    $this->actingAs($account)
        ->post(route('dashboard.widgets.store'), [
            'owner' => 'festival:'.$festival->id,
            'name' => 'Programme',
            'template' => 'list',
            'density' => 'compact',
        ])
        ->assertForbidden();
});

test('a festival widget shows the festival\'s events still to come, each saying where, in its colours', function () {
    $festival = Festival::factory()->create(['name' => 'Riverside Fest 2027', 'brand_colour' => '#ff69b4']);
    $widget = Widget::factory()->forFestival($festival)->create();
    $hall = Venue::factory()->create(['name' => 'Subscription Rooms', 'town' => 'Stroud']);

    Listing::factory()->create(['title' => 'Opening Night', 'start_date' => today()->addDay(), 'venue_id' => $hall->id, 'promoter_id' => $festival->promoter_id, 'festival_id' => $festival->id]);
    Listing::factory()->create(['title' => 'Last Year', 'start_date' => today()->subWeek(), 'promoter_id' => $festival->promoter_id, 'festival_id' => $festival->id]);
    Listing::factory()->create(['title' => 'Not In It', 'start_date' => today()->addDay(), 'promoter_id' => $festival->promoter_id]);

    $this->get(route('widgets.embed', $widget->key))
        ->assertOk()
        ->assertSee('Opening Night')
        ->assertSee('Subscription Rooms, Stroud')
        ->assertDontSee('Last Year')
        ->assertDontSee('Not In It')
        ->assertSee('--gop-brand: #ff69b4', false)
        ->assertSee('/listings/festival/'.$festival->id.'/riverside-fest-2027', false);
});

test('a festival widget takes up the festival\'s new colours', function () {
    $festival = Festival::factory()->create(['brand_colour' => '#ff69b4']);
    $widget = Widget::factory()->forFestival($festival)->create();
    Listing::factory()->create(['start_date' => today()->addDay(), 'promoter_id' => $festival->promoter_id, 'festival_id' => $festival->id]);

    $this->get(route('widgets.embed', $widget->key))->assertSee('--gop-brand: #ff69b4', false);

    $festival->update(['brand_colour' => '#1e90ff']);

    $this->get(route('widgets.embed', $widget->key))->assertSee('--gop-brand: #1e90ff', false);
});

test('once the festival has all been, its widget shows the programme from its first day', function () {
    $festival = Festival::factory()->create();
    $widget = Widget::factory()->forFestival($festival)->create();
    Listing::factory()->create(['title' => 'Opening Night', 'start_date' => today()->subDays(10), 'promoter_id' => $festival->promoter_id, 'festival_id' => $festival->id]);
    Listing::factory()->create(['title' => 'Closing Party', 'start_date' => today()->subDays(8), 'promoter_id' => $festival->promoter_id, 'festival_id' => $festival->id]);

    $this->get(route('widgets.embed', $widget->key))
        ->assertOk()
        ->assertSeeInOrder(['Opening Night', 'Closing Party']);
});

test('a listing joining or leaving a festival shows in its widget straight away', function () {
    $festival = Festival::factory()->create();
    $widget = Widget::factory()->forFestival($festival)->create();
    Listing::factory()->create(['title' => 'Opening Night', 'start_date' => today()->addDay(), 'promoter_id' => $festival->promoter_id, 'festival_id' => $festival->id]);
    $joining = Listing::factory()->create(['title' => 'Late Addition', 'start_date' => today()->addDays(2), 'promoter_id' => $festival->promoter_id]);

    $this->get(route('widgets.embed', $widget->key))->assertDontSee('Late Addition');

    $joining->update(['festival_id' => $festival->id]);

    $this->get(route('widgets.embed', $widget->key))->assertSee('Late Addition');

    $joining->update(['festival_id' => null]);

    $this->get(route('widgets.embed', $widget->key))->assertDontSee('Late Addition');
});
