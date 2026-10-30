<?php

use App\Models\Feature;
use App\Models\Page;
use App\Models\User;

/**
 * Our Writers is the masthead. It used to be generated from the writers
 * table; it is generated from the accounts the office has marked as
 * writing, which is the same people with somewhere to sign in.
 */
beforeEach(function () {
    Page::factory()->create(['slug' => 'our-writers', 'title' => 'Our Writers']);
});

test('the masthead lists the accounts marked as writing', function () {
    User::factory()->create(['name' => 'Paul Harper', 'is_writer' => true]);
    User::factory()->create(['name' => 'Siân Magill', 'is_writer' => true]);
    User::factory()->create(['name' => 'Adam Hinks', 'is_writer' => false]);

    $this->get('/our-writers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Our-writers')
            ->has('writers', 2)
            ->where('writers.0.name', 'Paul Harper')
            ->where('writers.1.name', 'Siân Magill')
        );
});

test('the masthead is set by first name', function () {
    User::factory()->create(['name' => 'Tom Berry', 'is_writer' => true]);
    User::factory()->create(['name' => 'Lucy White', 'is_writer' => true]);
    User::factory()->create(['name' => 'Anna Bailey', 'is_writer' => true]);

    $this->get('/our-writers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('writers.0.name', 'Anna Bailey')
            ->where('writers.1.name', 'Lucy White')
            ->where('writers.2.name', 'Tom Berry')
        );
});

/**
 * The column rather than hasRole(). Staff hold every role without being
 * marked with any, so asking the gate would put the whole office on the
 * masthead whether they have written anything or not.
 */
test('office staff are not put on the masthead by their access', function () {
    User::factory()->create(['name' => 'Alex Hobbis', 'email' => 'alex@goodonpaper.info']);

    $this->get('/our-writers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('writers', 0));
});

/**
 * A masthead is a list of names. The account behind one holds an email
 * address and a telephone number, and neither belongs on a public page.
 */
test('the masthead carries no contact details', function () {
    User::factory()->create([
        'name' => 'Paul Harper',
        'email' => 'paul@example.com',
        'phone' => '01453 000000',
        'is_writer' => true,
    ]);

    $response = $this->get('/our-writers')->assertOk();

    expect($response->getContent())
        ->not->toContain('paul@example.com')
        ->not->toContain('01453 000000');
});

/**
 * The same guard on a feature's byline, which is loaded with the article
 * on the home page, the features index and the article itself.
 */
test('a byline carries no contact details', function () {
    $writer = User::factory()->create([
        'name' => 'Robin Layfield',
        'email' => 'robin@ultrasimplified.com',
        'phone' => '01453 000000',
        'is_writer' => true,
        'bio' => 'Writes about music.',
    ]);

    $feature = Feature::factory()->create([
        'title' => 'Angine de Poitrine',
        'user_id' => $writer->id,
    ]);

    $response = $this
        ->get("/magazine/features/{$feature->id}/angine-de-poitrine")
        ->assertOk();

    expect($response->getContent())
        ->toContain('Robin Layfield')
        ->toContain('Writes about music.')
        ->not->toContain('robin@ultrasimplified.com')
        ->not->toContain('01453 000000');
});
