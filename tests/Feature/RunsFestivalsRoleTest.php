<?php

use App\Models\Festival;
use App\Models\Promoter;
use App\Models\User;

/**
 * Runs Festivals is granted in the Office like the other roles, but staff
 * do not hold it unless they tick it for themselves. What an account may
 * manage in Backstage is its own promoters' festivals, and only with it.
 */
test('staff can give an account Runs Festivals in the Office, and Backstage knows from the next page', function () {
    $promoterAccount = User::factory()->create(['name' => 'Pat Promoter', 'email' => 'pat@example.com']);
    Promoter::factory()->for($promoterAccount)->create();

    $this->actingAs($promoterAccount)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.roles.runs_festivals', false));

    $this->actingAs(User::factory()->create(['email' => 'robin@ultrasimplified.com']))
        ->putJson("/nova-api/users/{$promoterAccount->id}", [
            'name' => 'Pat Promoter',
            'email' => 'pat@example.com',
            'password' => '',
            'is_venue_mgr' => false,
            'is_promoter' => false,
            'is_writer' => false,
            'is_publisher' => false,
            'runs_festivals' => true,
        ])
        ->assertOk();

    expect($promoterAccount->fresh()->runs_festivals)->toBeTrue();

    $this->actingAs($promoterAccount->fresh())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.roles.runs_festivals', true));
});

test('a staff account manages no festivals until it ticks Runs Festivals on itself', function () {
    $staff = User::factory()->create(['email' => 'robin@ultrasimplified.com']);
    $festival = Festival::factory()->for(Promoter::factory()->for($staff))->create();

    expect($staff->managedFestivals()->pluck('id')->all())->toBe([]);

    $staff->forceFill(['runs_festivals' => true])->save();

    expect($staff->fresh()->managedFestivals()->pluck('id')->all())->toBe([$festival->id]);
});

test('an account with Runs Festivals manages its own promoters\' festivals and nobody else\'s', function () {
    $account = User::factory()->runsFestivals()->create();
    $own = Festival::factory()->for(Promoter::factory()->for($account))->create();
    Festival::factory()->create();

    expect($account->managedFestivals()->pluck('id')->all())->toBe([$own->id]);
});

test('an account without Runs Festivals manages none, even of its own promoters', function () {
    $account = User::factory()->create();
    Festival::factory()->for(Promoter::factory()->for($account))->create();

    expect($account->managedFestivals()->exists())->toBeFalse();
});
