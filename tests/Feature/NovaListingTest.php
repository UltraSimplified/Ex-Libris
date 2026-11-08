<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Nova\Filters\ListingPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Staff are identified by the viewNova gate, so these tests use an address
 * from that list rather than a role column, which the users table has not got.
 */
function staffUser(): User
{
    return User::factory()->create(['email' => 'robin@ultrasimplified.com']);
}

test('staff can manage every listing through nova', function () {
    $staff = staffUser();
    $magazineListing = Listing::factory()->create(['user_id' => null]);
    $promoterListing = Listing::factory()->for(User::factory())->create();

    foreach ([$magazineListing, $promoterListing] as $listing) {
        expect(Gate::forUser($staff)->check('view', $listing))->toBeTrue()
            ->and(Gate::forUser($staff)->check('update', $listing))->toBeTrue()
            ->and(Gate::forUser($staff)->check('delete', $listing))->toBeTrue()
            ->and(Gate::forUser($staff)->check('replicate', $listing))->toBeTrue();
    }

    expect(Gate::forUser($staff)->check('create', Listing::class))->toBeTrue();
});

test('a promoter cannot touch listings they do not own', function () {
    $promoter = User::factory()->create(['email' => 'promoter@example.com']);
    $magazineListing = Listing::factory()->create(['user_id' => null]);

    expect(Gate::forUser($promoter)->check('view', $magazineListing))->toBeFalse()
        ->and(Gate::forUser($promoter)->check('update', $magazineListing))->toBeFalse()
        ->and(Gate::forUser($promoter)->check('delete', $magazineListing))->toBeFalse()
        ->and(Gate::forUser($promoter)->check('replicate', $magazineListing))->toBeFalse();
});

test('a promoter keeps full control of their own listings', function () {
    $promoter = User::factory()->agreed()->create(['email' => 'promoter@example.com']);
    $own = Listing::factory()->for($promoter)->create();

    expect(Gate::forUser($promoter)->check('create', Listing::class))->toBeTrue()
        ->and(Gate::forUser($promoter)->check('view', $own))->toBeTrue()
        ->and(Gate::forUser($promoter)->check('update', $own))->toBeTrue()
        ->and(Gate::forUser($promoter)->check('delete', $own))->toBeTrue();
});

/*
 * Somebody who sent a listing in before the office linked them to
 * anything can still read and delete it, but not add, change or copy one:
 * what is saved now goes straight up.
 */
test('an account the office has not agreed can read and delete its listings, but not publish', function () {
    $sender = User::factory()->create();
    $own = Listing::factory()->for($sender)->create();

    expect(Gate::forUser($sender)->check('create', Listing::class))->toBeFalse()
        ->and(Gate::forUser($sender)->check('update', $own))->toBeFalse()
        ->and(Gate::forUser($sender)->check('replicate', $own))->toBeFalse()
        ->and(Gate::forUser($sender)->check('view', $own))->toBeTrue()
        ->and(Gate::forUser($sender)->check('delete', $own))->toBeTrue();
});

test('an ownerless listing is never owned by a promoter', function () {
    $promoter = User::factory()->create(['email' => 'promoter@example.com']);
    $orphan = Listing::factory()->create(['user_id' => null]);

    expect(Gate::forUser($promoter)->check('update', $orphan))->toBeFalse();
});

test('the index filter splits listings on their last day', function () {
    // Finished yesterday, still running, and yet to start.
    $past = Listing::factory()->create(['start_date' => now()->subDays(10), 'end_date' => now()->subDay()]);
    $running = Listing::factory()->create(['start_date' => now()->subDays(10), 'end_date' => now()->addDays(5)]);
    $future = Listing::factory()->create(['start_date' => now()->addMonth(), 'end_date' => null]);
    $today = Listing::factory()->create(['start_date' => now(), 'end_date' => null]);

    $filter = new ListingPeriod;
    $request = NovaRequest::create('/');

    $ids = fn (string $value) => $filter->apply($request, Listing::query(), $value)->pluck('id')->all();

    expect($ids('upcoming'))->toEqualCanonicalizing([$running->id, $future->id, $today->id])
        ->and($ids('past'))->toEqualCanonicalizing([$past->id])
        ->and($ids('all'))->toHaveCount(4);
});

test('the filter defaults to current and upcoming', function () {
    expect((new ListingPeriod)->default())->toBe('upcoming');
});

test('a replicated listing drops the original slug so it regenerates', function () {
    $listing = Listing::factory()->create(['title' => 'Los Fastidios']);

    expect($listing->slug)->toBe('los-fastidios');

    $copy = (new App\Nova\Listing($listing))->replicate()->model();

    expect($copy->slug)->toBeNull();

    $copy->title = 'Los Fastidios: Second Night';
    $copy->save();

    expect($copy->fresh()->slug)->toBe('los-fastidios-second-night')
        ->and($copy->id)->not->toBe($listing->id);
});

/*
 * An event with nothing to book has nothing to put on a button, so the
 * Office's button wording is left blank. Laravel turns a blank field into
 * null on the way in, and the column would not take one: saving the
 * listing failed on the database rather than in the form.
 */
test('a listing saves from the office with its button text left blank', function () {
    $staff = staffUser();
    $category = Category::factory()->create();

    $this->actingAs($staff)
        ->postJson('/nova-api/listings', [
            'title' => 'John Ingledew: Inspired!',
            'category' => $category->id,
            'body' => 'A launch and an exhibition.',
            'start_date' => '2026-11-06',
            'ticket_price' => 'Free',
            'button_text' => '',
        ])
        ->assertSuccessful();

    expect(Listing::sole()->button_text)->toBeNull();
});
/*
 * Specific days in the Office.
 *
 * The Office used to show a listing's specific days read-only, with a link
 * to the Dashboard, keep its dates read-only while it had them, and copy
 * them onto a replicated listing after it was saved. It now edits them on
 * the same calendar as the Dashboard (office-specific-days-editor), so the
 * tests below are those of the new spec rather than the old one.
 */

/**
 * The fields the Office shows on a listing's own page.
 *
 * @return Collection<int, array<string, mixed>>
 */
function officeDetailFields(Listing $listing): Collection
{
    return collect(test()->actingAs(staffUser())
        ->getJson('/nova-api/listings/'.$listing->id)
        ->assertOk()
        ->json('resource.fields'));
}

/**
 * What the Office's calendar sends.
 */
function calendarValue(array $days, bool $enabled = true): string
{
    return json_encode(['enabled' => $enabled, 'days' => $days]);
}

/**
 * The days a listing has, earliest first.
 */
function officeDaysOf(Listing $listing): array
{
    return $listing->fresh()->days->map(fn ($day) => $day->day->toDateString())->all();
}

/**
 * An Office save of a listing, as Nova's edit form sends it.
 */
function officeUpdate(Listing $listing, array $changes): TestResponse
{
    return test()->actingAs(staffUser())->putJson('/nova-api/listings/'.$listing->id.'?editing=true&editMode=update', [
        'title' => $listing->title,
        'category' => $listing->category_id,
        'body' => $listing->body,
        'start_date' => $listing->start_date->toDateString(),
        'end_date' => $listing->end_date?->toDateString(),
        ...$changes,
    ]);
}

test('the office lists a listing\'s specific days on its page', function () {
    $quiz = Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();

    $field = officeDetailFields($quiz)->firstWhere('name', 'Specific days');

    expect($field['displayedAs'])->toBe('3 Nov, 17 Nov, 1 Dec, 15 Dec 2026');
});

test('specific days running into another year each carry their year in the office', function () {
    $listing = Listing::factory()->onDays('2026-12-15', '2027-01-05')->create();

    $field = officeDetailFields($listing)->firstWhere('name', 'Specific days');

    expect($field['displayedAs'])->toBe('15 Dec 2026, 5 Jan 2027');
});

test('a listing on every day of its run has no specific days in the office', function () {
    $listing = Listing::factory()->create(['start_date' => '2026-11-02', 'end_date' => '2026-11-30']);

    expect(officeDetailFields($listing)->firstWhere('name', 'Specific days'))->toBeNull();
});

test('an office save with specific days pulls the run in to them and keeps them', function () {
    $festival = Listing::factory()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-20']);

    officeUpdate($festival, ['specific_days' => calendarValue(['2026-06-06', '2026-06-07', '2026-06-13', '2026-06-14', '2026-06-20'])])
        ->assertOk();

    expect($festival->fresh()->start_date->toDateString())->toBe('2026-06-06')
        ->and($festival->fresh()->end_date->toDateString())->toBe('2026-06-20')
        ->and(officeDaysOf($festival))->toBe(['2026-06-06', '2026-06-07', '2026-06-13', '2026-06-14', '2026-06-20']);
});

test('an office save with specific days unticked puts the listing back on every day', function () {
    $quiz = Listing::factory()->onDays('2026-11-03', '2026-11-17')->create();

    officeUpdate($quiz, ['specific_days' => calendarValue(['2026-11-03'], enabled: false)])
        ->assertOk();

    expect(officeDaysOf($quiz))->toBe([])
        ->and($quiz->fresh()->start_date->toDateString())->toBe('2026-11-03')
        ->and($quiz->fresh()->end_date->toDateString())->toBe('2026-11-17');
});

test('the dates of a listing on specific days can be changed in the office along with its days', function () {
    $quiz = Listing::factory()->onDays('2026-11-03', '2026-12-15')->create();

    officeUpdate($quiz, ['end_date' => '2026-12-31', 'specific_days' => calendarValue(['2026-11-03', '2026-12-29'])])
        ->assertOk();

    expect($quiz->fresh()->end_date->toDateString())->toBe('2026-12-29')
        ->and(officeDaysOf($quiz))->toBe(['2026-11-03', '2026-12-29']);
});

test('an office save that does not send the calendar leaves the days as they are', function () {
    $quiz = Listing::factory()->onDays('2026-11-03', '2026-11-17')->create();

    officeUpdate($quiz, ['title' => 'Pub Quiz, renamed'])
        ->assertOk();

    expect($quiz->fresh()->title)->toBe('Pub Quiz, renamed')
        ->and(officeDaysOf($quiz))->toBe(['2026-11-03', '2026-11-17']);
});

test('an office save with specific days that will not do is refused with the dashboard\'s message', function (array $changes, string $message) {
    $listing = Listing::factory()->create(['title' => 'Weekend festival', 'start_date' => '2026-06-01', 'end_date' => '2026-06-20']);

    officeUpdate($listing, $changes)
        ->assertJsonValidationErrors(['specific_days' => $message]);

    expect($listing->fresh()->days)->toBeEmpty();
})->with([
    'no day ticked' => [['specific_days' => calendarValue([])], 'Choose at least one day, or untick "Set specific days".'],
    'a day outside the run' => [['specific_days' => calendarValue(['2026-06-21'])], 'Each chosen day must fall between the start and end dates.'],
    'no end date' => [['end_date' => null, 'specific_days' => calendarValue(['2026-06-06'])], 'Add an end date to choose specific days.'],
]);

/**
 * The pub quiz, to be copied.
 */
function replicableQuiz(): Listing
{
    return Listing::factory()
        ->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')
        ->create(['title' => 'Pub Quiz']);
}

/**
 * A copy saved from the Office's Replicate form, with the calendar as the
 * form would send it.
 */
function officeReplicate(Listing $original, string $startDate, string $endDate, string $calendar): TestResponse
{
    return test()->actingAs(staffUser())->postJson('/nova-api/listings?editing=true&editMode=create', [
        'title' => 'Copy of '.$original->title,
        'category' => $original->category_id,
        'body' => $original->body,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'specific_days' => $calendar,
        'fromResourceId' => $original->id,
    ]);
}

/*
 * Asked for as Nova's own form asks, a plain GET that accepts JSON. getJson()
 * also sends a JSON body, which the line-up and gallery fields (Flexible
 * Content) try to read as their content and fail on.
 */
test('the office replicate form opens with the original\'s specific days ticked', function () {
    $quiz = replicableQuiz();

    $fields = collect($this->actingAs(staffUser())
        ->get('/nova-api/listings/creation-fields?editing=true&editMode=create&fromResourceId='.$quiz->id, ['Accept' => 'application/json'])
        ->assertOk()
        ->json('fields'));

    expect($fields->firstWhere('attribute', 'specific_days')['value'])
        ->toBe(['enabled' => true, 'days' => ['2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15']]);
});

test('a copy saved from the replicate form keeps the original\'s specific days and run', function () {
    $quiz = replicableQuiz();

    officeReplicate($quiz, '2026-11-03', '2026-12-15', calendarValue(['2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15']))
        ->assertCreated();

    $copy = Listing::query()->where('title', 'Copy of Pub Quiz')->sole();

    expect($copy->start_date->toDateString())->toBe('2026-11-03')
        ->and($copy->end_date->toDateString())->toBe('2026-12-15')
        ->and(officeDaysOf($copy))->toBe(['2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15']);
});

test('a copy moved past every specific day with the box still ticked is refused', function () {
    $quiz = replicableQuiz();

    officeReplicate($quiz, '2027-01-05', '2027-01-26', calendarValue([]))
        ->assertJsonValidationErrors(['specific_days' => 'Choose at least one day, or untick "Set specific days".']);

    expect(Listing::query()->where('title', 'Copy of Pub Quiz')->exists())->toBeFalse();
});

test('a copy moved past every specific day and unticked keeps its dates and has none', function () {
    $quiz = replicableQuiz();

    officeReplicate($quiz, '2027-01-05', '2027-01-26', calendarValue([], enabled: false))
        ->assertCreated();

    $copy = Listing::query()->where('title', 'Copy of Pub Quiz')->sole();

    expect($copy->start_date->toDateString())->toBe('2027-01-05')
        ->and($copy->end_date->toDateString())->toBe('2027-01-26')
        ->and(officeDaysOf($copy))->toBe([]);
});
