<?php

use App\Models\Listing;
use App\Models\User;
use App\Models\Venue;
use App\Models\Widget;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']));
});

/**
 * Whether the Office index offers the plain delete for each venue, by id.
 *
 * @return array<int, bool>
 */
function venueDeletability(): array
{
    return collect(test()->getJson('/nova-api/venues')->assertOk()->json('resources'))
        ->mapWithKeys(fn (array $row) => [$row['id']['value'] => $row['authorizedToDelete']])
        ->all();
}

test('a venue with listings is not offered the plain delete, and one without is', function () {
    $busy = Venue::factory()->create();
    $empty = Venue::factory()->create();
    Listing::factory()->for($busy)->create();

    expect(venueDeletability())->toMatchArray([$busy->id => false, $empty->id => true]);
});

test('the plain delete leaves a venue with listings in place rather than erroring', function () {
    $venue = Venue::factory()->create();
    Listing::factory()->for($venue)->create();

    $this->deleteJson('/nova-api/venues?resources[]='.$venue->id)->assertOk();

    expect(Venue::find($venue->id))->not->toBeNull();
});

test('deleting a venue moves its listings and widgets to the venue chosen', function () {
    $duplicate = Venue::factory()->create(['name' => 'SVA John Street Gallery']);
    $real = Venue::factory()->create(['name' => 'SVA John Street']);
    $listings = Listing::factory()->count(2)->for($duplicate)->create();
    $widget = Widget::factory()->create(['owner_id' => $duplicate->id]);

    $this->postJson('/nova-api/venues/action?action=delete-venue', [
        'resources' => (string) $duplicate->id,
        'move_to' => (string) $real->id,
    ])->assertOk()->assertJsonPath('message', "Deleted 'SVA John Street Gallery' and moved 2 listings to 'SVA John Street'.");

    expect(Venue::find($duplicate->id))->toBeNull()
        ->and($listings->map->fresh()->pluck('venue_id')->unique()->all())->toBe([$real->id])
        ->and($widget->fresh()->owner_id)->toBe($real->id);
});

test('deleting a venue with nowhere chosen leaves its listings without a venue', function () {
    $venue = Venue::factory()->create();
    $listing = Listing::factory()->for($venue)->create();
    $widget = Widget::factory()->create(['owner_id' => $venue->id]);

    $this->postJson('/nova-api/venues/action?action=delete-venue', [
        'resources' => (string) $venue->id,
        'move_to' => '',
    ])->assertOk();

    expect(Venue::find($venue->id))->toBeNull()
        ->and($listing->fresh()->venue_id)->toBeNull()
        ->and($listing->fresh()->venue->name)->toBe('Venue to be confirmed')
        ->and(Widget::find($widget->id))->toBeNull();
});

test('listings cannot be moved to a venue that is being deleted too', function () {
    $first = Venue::factory()->create();
    $second = Venue::factory()->create();
    $listing = Listing::factory()->for($first)->create();

    $this->postJson('/nova-api/venues/action?action=delete-venue', [
        'resources' => [$first->id, $second->id],
        'move_to' => (string) $second->id,
    ])->assertOk()->assertJsonStructure(['danger']);

    expect(Venue::whereKey([$first->id, $second->id])->count())->toBe(2)
        ->and($listing->fresh()->venue_id)->toBe($first->id);
});
