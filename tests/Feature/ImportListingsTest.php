<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Models\Venue;

/**
 * The magazine import runs against the live database, so what it leaves out
 * matters as much as what it adds. Listings people put in themselves come
 * with pictures and take precedence over the magazine's version of the same
 * event, but they seldom match it exactly: a run that began earlier starts on
 * another day, and a title picks up words and a full stop on the way.
 */
beforeEach(function () {
    // The importer files listings under the magazine's eight fixed category ids.
    Category::factory()->create(['id' => 1]);
});

afterEach(function () {
    @unlink(importFile());
});

function importFile(): string
{
    return sys_get_temp_dir().'/listings-import-'.getmypid().'.json';
}

/**
 * Write events the way the listings parser does, and name the command that
 * imports them.
 *
 * @param  array<int, array<string, mixed>>  $events
 */
function importCommand(array $events, string $options = '--commit'): string
{
    file_put_contents(importFile(), json_encode($events));

    return trim('listings:import '.importFile().' '.$options);
}

/**
 * One event as the parser writes it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function magazineEvent(string $venue, array $overrides = []): array
{
    return [
        'venue' => $venue,
        'category' => 'ART + CRAFTS',
        'title' => 'Fabric Swap',
        'body' => 'Bring your unwanted fabric and swap it for something new.',
        'start_date' => '2026-10-23',
        'end_date' => null,
        'dates_raw' => 'Fri 23',
        'event_time_description' => '5-7pm',
        'ticket_price' => 'Free',
        'event_link' => 'emergencecommons.co.uk',
        ...$overrides,
    ];
}

/**
 * A listing somebody put in through the dashboard: an account and a cover image.
 *
 * @param  array<string, mixed>  $attributes
 */
function handAddedListing(Venue $venue, array $attributes = []): Listing
{
    return Listing::factory()->for($venue)->for(User::factory())->create([
        'thumbnail' => 'covers/cover.jpg',
        ...$attributes,
    ]);
}

test('a dry run reports what it would add and writes nothing', function () {
    Venue::factory()->create(['name' => 'Emergence Commons']);

    $this->artisan(importCommand([magazineEvent('Emergence Commons')], options: ''))
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();

    expect(Listing::count())->toBe(0);
});

test('a committed run adds each listing with what the magazine gave', function () {
    $venue = Venue::factory()->create(['name' => 'Emergence Commons']);

    $this->artisan(importCommand([magazineEvent('Emergence Commons', [
        'end_date' => '2026-10-30',
        'dates_raw' => 'Fri 2/9/16/23/30',
    ])]))->assertSuccessful();

    $listing = Listing::sole();

    expect($listing->title)->toBe('Fabric Swap')
        ->and($listing->venue_id)->toBe($venue->id)
        ->and($listing->category_id)->toBe(1)
        ->and($listing->start_date->toDateString())->toBe('2026-10-23')
        ->and($listing->end_date->toDateString())->toBe('2026-10-30')
        ->and($listing->additional_date_information)->toBe('Fri 2/9/16/23/30')
        ->and($listing->event_time_description)->toBe('5-7pm')
        ->and($listing->ticket_price)->toBe('Free')
        ->and($listing->event_link)->toBe('https://emergencecommons.co.uk')
        ->and($listing->button_text)->toBe('More information')
        ->and($listing->user_id)->toBeNull();
});

test('a listing already there with the same title and start date is left alone', function () {
    $venue = Venue::factory()->create(['name' => 'Gallery Pangolin']);
    Listing::factory()->for($venue)->create(['title' => 'Jason Wason at 80', 'start_date' => '2026-09-05']);

    $this->artisan(importCommand([magazineEvent('Gallery Pangolin', [
        'title' => 'Jason Wason at 80',
        'start_date' => '2026-09-05',
    ])]))
        ->expectsOutputToContain('already in database')
        ->assertSuccessful();

    expect(Listing::count())->toBe(1);
});

test('an unknown venue is created with --create-venues and the listing filed under it', function () {
    $this->artisan(importCommand([magazineEvent('Box Village Hall')], '--commit --create-venues'))
        ->assertSuccessful();

    $venue = Venue::firstWhere('name', 'Box Village Hall');

    expect($venue)->not->toBeNull()
        ->and(Listing::sole()->venue_id)->toBe($venue->id);
});

test('without --create-venues an unknown venue leaves the listing unassigned', function () {
    $this->artisan(importCommand([magazineEvent('Box Village Hall')]))
        ->expectsOutputToContain('No venue record')
        ->assertSuccessful();

    expect(Venue::count())->toBe(0)
        ->and(Listing::sole()->venue_id)->toBeNull();
});

test('a hand-added listing takes precedence over the magazine version of the same event', function (string $handAdded, string $magazine) {
    $venue = Venue::factory()->create(['name' => 'Emergence Commons']);
    $existing = handAddedListing($venue, ['title' => $handAdded, 'start_date' => '2026-10-23']);

    // One expectation, because an output line satisfies only the first one that matches it.
    $this->artisan(importCommand([magazineEvent('Emergence Commons', ['title' => $magazine])]))
        ->expectsOutputToContain("[hand-added listing takes precedence] 2026-10-23  {$magazine}  (#{$existing->id} ")
        ->assertSuccessful();

    expect(Listing::count())->toBe(1);
})->with([
    'more words and a full stop' => ['Fabric Swap at Emergence Commons.', 'Fabric Swap'],
    'capital letters' => ['DECORATIVE ARTS WITH POMME', 'Decorative Arts with Pomme'],
    'the same words in another order' => ['Stitch Social Banner Making', 'Banner Making with Stitch Social'],
    'a slip of the keyboard' => ['Wild Wooly Women', 'Wild Woolly Women'],
    'an ampersand against an and' => ['Dahl and Dance', 'Dahl & Dance'],
]);

test('a hand-added run that began before the magazine says takes precedence', function () {
    $venue = Venue::factory()->create(['name' => 'Emergence Commons']);
    handAddedListing($venue, [
        'title' => 'DECORATIVE ARTS WITH POMME',
        'start_date' => '2026-09-23',
        'end_date' => '2026-10-28',
    ]);

    $this->artisan(importCommand([magazineEvent('Emergence Commons', [
        'title' => 'Decorative Arts with Pomme',
        'start_date' => '2026-10-07',
        'end_date' => '2026-10-28',
    ])]))
        ->expectsOutputToContain('hand-added listing takes precedence')
        ->doesntExpectOutputToContain('partly covered')
        ->assertSuccessful();

    expect(Listing::count())->toBe(1);
});

test('the days a hand-added listing leaves out are reported', function () {
    $venue = Venue::factory()->create(['name' => 'Emergence Commons']);
    $existing = handAddedListing($venue, [
        'title' => 'Late Night Fridays at Emergence Commons',
        'start_date' => '2026-09-28',
        'end_date' => '2026-10-03',
    ]);

    $this->artisan(importCommand([magazineEvent('Emergence Commons', [
        'title' => 'Late Night Fridays',
        'start_date' => '2026-10-02',
        'end_date' => '2026-10-23',
        'dates_raw' => 'Fri 2/Fri 23',
    ])]))
        ->expectsOutputToContain('partly covered')
        ->expectsOutputToContain("#{$existing->id} has 2026-09-28 to 2026-10-03")
        ->assertSuccessful();

    expect(Listing::count())->toBe(1);
});

test('a dry run names the hand-added listings it would leave out', function () {
    $venue = Venue::factory()->create(['name' => 'Emergence Commons']);
    handAddedListing($venue, ['title' => 'Fabric Swap at Emergence Commons.', 'start_date' => '2026-10-23']);

    $this->artisan(importCommand([magazineEvent('Emergence Commons')], options: ''))
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('hand-added listing takes precedence')
        ->assertSuccessful();
});

test('a listing counts as hand-added with an account or with a cover image', function (string $kind) {
    $venue = Venue::factory()->create(['name' => 'Emergence Commons']);
    Listing::factory()->for($venue)->create([
        'title' => 'Fabric Swap at Emergence Commons.',
        'start_date' => '2026-10-23',
        ...($kind === 'account' ? ['user_id' => User::factory()->create()->id] : ['thumbnail' => 'covers/cover.jpg']),
    ]);

    $this->artisan(importCommand([magazineEvent('Emergence Commons')]))
        ->expectsOutputToContain('hand-added listing takes precedence')
        ->assertSuccessful();

    expect(Listing::count())->toBe(1);
})->with([
    'an account and no cover image' => 'account',
    'a cover image and no account' => 'image',
]);

test('a cover image column left blank does not make a listing hand-added', function () {
    $venue = Venue::factory()->create(['name' => 'Emergence Commons']);
    Listing::factory()->for($venue)->create([
        'title' => 'Fabric Swap at Emergence Commons.',
        'start_date' => '2026-10-23',
        'thumbnail' => '',
    ]);

    $this->artisan(importCommand([magazineEvent('Emergence Commons')]))->assertSuccessful();

    expect(Listing::count())->toBe(2);
});

test('a hand-added listing is found through the magazine name for the venue', function () {
    $venue = Venue::factory()->create(['name' => 'Lansdown Hall']);
    handAddedListing($venue, ['title' => 'Sister Act II', 'start_date' => '2026-09-30', 'end_date' => '2026-10-04']);

    $this->artisan(importCommand([magazineEvent('Lansdown Gallery', [
        'title' => 'Sister Act II',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-04',
    ])]))
        ->expectsOutputToContain('hand-added listing takes precedence')
        ->assertSuccessful();

    expect(Listing::count())->toBe(1);
});

test('a hand-added listing that is not this event does not stop it being imported', function (string $title, string $start, ?string $end, bool $sameVenue) {
    $venue = Venue::factory()->create(['name' => 'Emergence Commons']);
    $elsewhere = Venue::factory()->create(['name' => 'Lansdown Hall']);
    handAddedListing($sameVenue ? $venue : $elsewhere, ['title' => $title, 'start_date' => $start, 'end_date' => $end]);

    $this->artisan(importCommand([magazineEvent('Emergence Commons')]))->assertSuccessful();

    expect(Listing::count())->toBe(2);
})->with([
    'a different event the same day' => ['Elanor Moss', '2026-10-23', null, true],
    'the same event on other days' => ['Fabric Swap', '2026-09-01', '2026-09-03', true],
    'the same event at another venue' => ['Fabric Swap at Lansdown Hall', '2026-10-23', null, false],
]);

test('an ordinary imported listing with a similar title does not stop a new run being imported', function () {
    $venue = Venue::factory()->create(['name' => 'House of Goblin']);
    Listing::factory()->for($venue)->create([
        'title' => 'Throwing-on-Wheel',
        'start_date' => '2026-09-08',
        'end_date' => '2026-10-13',
    ]);

    $this->artisan(importCommand([magazineEvent('House Of Goblin', [
        'title' => 'Throwing On Wheel',
        'start_date' => '2026-10-08',
        'end_date' => '2026-10-20',
    ])]))->assertSuccessful();

    expect(Listing::count())->toBe(2);
});
