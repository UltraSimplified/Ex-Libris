<?php

use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;

/**
 * A promoter writing up an event at a hall the magazine has never listed
 * adds the place from the listing form, rather than stopping to ask the
 * office and losing what they had typed.
 */
test('a promoter can add a venue from the listing form', function () {
    $user = User::factory()->has(Promoter::factory())->create();

    $this->actingAs($user)
        ->postJson(route('dashboard.venues.store'), [
            'name' => 'Lansdown Hall',
            'town' => 'Stroud',
            'address' => 'Lansdown',
        ])
        ->assertCreated()
        ->assertJson(['name' => 'Lansdown Hall', 'town' => 'Stroud', 'owned' => false]);

    $venue = Venue::firstWhere('name', 'Lansdown Hall');

    // Naming a place is not a claim to run it.
    expect($venue->user_id)->toBeNull()
        ->and($venue->address)->toBe('Lansdown');
});

test('somebody who speaks for no promoter cannot add a venue', function () {
    $venueOwner = User::factory()->create();
    Venue::factory()->for($venueOwner)->create();

    $this->actingAs($venueOwner)
        ->postJson(route('dashboard.venues.store'), ['name' => 'Somewhere New'])
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->postJson(route('dashboard.venues.store'), ['name' => 'Somewhere New'])
        ->assertForbidden();

    expect(Venue::where('name', 'Somewhere New')->exists())->toBeFalse();
});

test('guests cannot add a venue', function () {
    $this->postJson(route('dashboard.venues.store'), ['name' => 'Somewhere New'])
        ->assertUnauthorized();
});

test('a venue needs a name', function () {
    $user = User::factory()->has(Promoter::factory())->create();

    $this->actingAs($user)
        ->postJson(route('dashboard.venues.store'), ['name' => ''])
        ->assertJsonValidationErrors('name');
});

test('the same place in the same town is handed back rather than duplicated', function () {
    $user = User::factory()->has(Promoter::factory())->create();
    $existing = Venue::factory()->create(['name' => 'Lansdown Hall', 'town' => 'Stroud']);

    $this->actingAs($user)
        ->postJson(route('dashboard.venues.store'), [
            'name' => 'lansdown hall',
            'town' => 'STROUD',
        ])
        ->assertCreated()
        ->assertJson(['id' => $existing->id]);

    expect(Venue::where('name', 'like', '%ansdown%')->count())->toBe(1);
});

test('the same name in another town is a different venue', function () {
    $user = User::factory()->has(Promoter::factory())->create();
    Venue::factory()->create(['name' => 'The Swan', 'town' => 'Stroud']);

    $this->actingAs($user)
        ->postJson(route('dashboard.venues.store'), [
            'name' => 'The Swan',
            'town' => 'Nailsworth',
        ])
        ->assertCreated();

    expect(Venue::where('name', 'The Swan')->count())->toBe(2);
});
