<?php

use App\Models\Page;
use App\Models\User;

/**
 * The masthead is set by surname. It used to be generated from a writers
 * table; writers are accounts now, so the scope lives on User and these
 * cover it there.
 *
 * Surnames here are consistently capitalised on purpose: SQLite sorts with a
 * binary collation while MySQL's utf8mb4_unicode_ci is case-insensitive, so
 * mixed casing would assert on driver behaviour rather than on the scope.
 */
test('writers are ordered by the last word of their name', function () {
    User::factory()->writer()->create(['name' => 'Jo Bloggs']);
    User::factory()->writer()->create(['name' => 'Mary Jane Watson']);
    User::factory()->writer()->create(['name' => 'Sam Adams']);

    expect(User::orderBySurname()->pluck('name')->all())
        ->toBe(['Sam Adams', 'Jo Bloggs', 'Mary Jane Watson']);
});

test('the sort direction can be reversed', function () {
    User::factory()->writer()->create(['name' => 'Jo Bloggs']);
    User::factory()->writer()->create(['name' => 'Sam Adams']);

    expect(User::orderBySurname('desc')->pluck('name')->all())
        ->toBe(['Jo Bloggs', 'Sam Adams']);
});

test('single word names are sorted on the whole name', function () {
    User::factory()->writer()->create(['name' => 'Prince']);
    User::factory()->writer()->create(['name' => 'Sam Adams']);

    expect(User::orderBySurname()->pluck('name')->all())
        ->toBe(['Sam Adams', 'Prince']);
});

test('the scope is chainable with other query methods', function () {
    User::factory()->writer()->create(['name' => 'Jo Bloggs']);
    User::factory()->writer()->create(['name' => 'Sam Adams']);
    User::factory()->writer()->create(['name' => 'Kit Zeller']);

    expect(User::orderBySurname()->limit(2)->pluck('name')->all())
        ->toBe(['Sam Adams', 'Jo Bloggs']);
});

test('an invalid sort direction is rejected', function () {
    User::orderBySurname('; drop table users --')->get();
})->throws(InvalidArgumentException::class);

test('the scope narrows to the writers rather than every account', function () {
    User::factory()->writer()->create(['name' => 'Sam Adams']);
    User::factory()->create(['name' => 'Jo Bloggs', 'is_writer' => false]);

    expect(User::writers()->orderBySurname()->pluck('name')->all())
        ->toBe(['Sam Adams']);
});

/**
 * The masthead itself is read by first name, and so are the writers'
 * own pages; surname order stays available as a scope.
 */
test('the our writers page lists writers by first name', function () {
    // The page is reached by slug now, so the row has to be there.
    Page::factory()->create(['title' => 'Our writers', 'slug' => 'our-writers']);

    User::factory()->writer()->create(['name' => 'Jo Bloggs']);
    User::factory()->writer()->create(['name' => 'Sam Adams']);
    User::factory()->writer()->create(['name' => 'Kit Zeller']);

    $this->get('/our-writers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Our-writers')
            ->where('writers.0.name', 'Jo Bloggs')
            ->where('writers.1.name', 'Kit Zeller')
            ->where('writers.2.name', 'Sam Adams')
        );
});
