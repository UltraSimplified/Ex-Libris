<?php

use App\Models\Listing;
use App\Models\ListingDay;
use App\Services\Listings\ListingDays;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

function savedDays(Listing $listing): array
{
    return $listing->fresh()->days
        ->map(fn (ListingDay $day): string => $day->day->toDateString())
        ->all();
}

test('no chosen days leaves the run as typed and keeps no days', function () {
    $typed = ['title' => 'Life drawing', 'start_date' => '2026-11-02', 'end_date' => '2026-11-30'];

    $result = (new ListingDays)->normalise($typed, []);

    expect($result)->toBe(['attributes' => $typed, 'days' => []]);
});

test('one chosen day makes a one-day listing on that day', function () {
    $result = (new ListingDays)->normalise(['start_date' => '2026-06-01', 'end_date' => '2026-06-20'], ['2026-06-13']);

    expect($result)->toBe([
        'attributes' => ['start_date' => '2026-06-13', 'end_date' => null],
        'days' => [],
    ]);
});

test('chosen days pull the run in to the first and last of them', function (array $typed, array $chosen, array $saved) {
    $result = (new ListingDays)->normalise($typed, $chosen);

    expect($result)->toBe($saved);
})->with([
    'the pub quiz every other Tuesday' => [
        ['start_date' => '2026-11-01', 'end_date' => '2026-12-24'],
        ['2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15'],
        [
            'attributes' => ['start_date' => '2026-11-03', 'end_date' => '2026-12-15'],
            'days' => ['2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15'],
        ],
    ],
    'the festival at weekends' => [
        ['start_date' => '2026-06-01', 'end_date' => '2026-06-20'],
        ['2026-06-06', '2026-06-07', '2026-06-13', '2026-06-14', '2026-06-20'],
        [
            'attributes' => ['start_date' => '2026-06-06', 'end_date' => '2026-06-20'],
            'days' => ['2026-06-06', '2026-06-07', '2026-06-13', '2026-06-14', '2026-06-20'],
        ],
    ],
    'days sent out of order and twice' => [
        ['start_date' => '2026-11-01', 'end_date' => '2026-11-30'],
        ['2026-11-17', '2026-11-03', '2026-11-17'],
        [
            'attributes' => ['start_date' => '2026-11-03', 'end_date' => '2026-11-17'],
            'days' => ['2026-11-03', '2026-11-17'],
        ],
    ],
]);

test('every day of the run chosen keeps no days', function (array $chosen, array $run) {
    $result = (new ListingDays)->normalise(['start_date' => '2026-11-02', 'end_date' => '2026-11-30'], $chosen);

    expect($result)->toBe(['attributes' => $run, 'days' => []]);
})->with([
    'every day, as Select all leaves it' => [
        array_map(fn (int $day): string => sprintf('2026-11-%02d', $day), range(2, 30)),
        ['start_date' => '2026-11-02', 'end_date' => '2026-11-30'],
    ],
    'an unbroken block from the middle' => [
        ['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14'],
        ['start_date' => '2026-11-10', 'end_date' => '2026-11-14'],
    ],
]);

test('saving keeps the listing and its days together', function () {
    $listing = Listing::factory()->onDays('2026-11-05', '2026-11-12')->create();

    (new ListingDays)->save($listing, ['start_date' => '2026-11-01', 'end_date' => '2026-12-24'], ['2026-11-03', '2026-11-17']);

    expect($listing->fresh()->start_date->toDateString())->toBe('2026-11-03')
        ->and($listing->fresh()->end_date->toDateString())->toBe('2026-11-17')
        ->and(savedDays($listing))->toBe(['2026-11-03', '2026-11-17']);
});

test('saving with no days clears the days a listing had', function () {
    $listing = Listing::factory()->onDays('2026-11-03', '2026-11-17')->create();

    (new ListingDays)->save($listing, ['start_date' => '2026-11-03', 'end_date' => '2026-11-17'], []);

    expect(savedDays($listing))->toBe([]);
});

test('a listing whose days cannot be written is not saved either', function () {
    $listing = Listing::factory()->create(['title' => 'Pub quiz']);
    Schema::drop('listing_days');

    $save = fn () => (new ListingDays)->save(
        $listing,
        ['title' => 'Pub quiz, every other Tuesday', 'start_date' => '2026-11-01', 'end_date' => '2026-12-24'],
        ['2026-11-03', '2026-11-17'],
    );

    expect($save)->toThrow(QueryException::class)
        ->and($listing->fresh()->title)->toBe('Pub quiz');
});

test('a copy on the original dates gets all of its days', function () {
    $original = Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();
    $copy = Listing::factory()->create(['start_date' => '2026-11-03', 'end_date' => '2026-12-15']);

    (new ListingDays)->copy($original, $copy);

    expect(savedDays($copy))->toBe(['2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15']);
});

test('a copy gets only the days inside its own run, and its run is pulled in to them', function () {
    $original = Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();
    $copy = Listing::factory()->create(['start_date' => '2026-11-10', 'end_date' => '2026-12-10']);

    (new ListingDays)->copy($original, $copy);

    expect(savedDays($copy))->toBe(['2026-11-17', '2026-12-01'])
        ->and($copy->fresh()->start_date->toDateString())->toBe('2026-11-17')
        ->and($copy->fresh()->end_date->toDateString())->toBe('2026-12-01');
});

test('a copy moved past every day keeps its dates and gets no days', function () {
    $original = Listing::factory()->onDays('2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15')->create();
    $copy = Listing::factory()->create(['start_date' => '2027-01-05', 'end_date' => '2027-01-26']);

    (new ListingDays)->copy($original, $copy);

    expect(savedDays($copy))->toBe([])
        ->and($copy->fresh()->start_date->toDateString())->toBe('2027-01-05')
        ->and($copy->fresh()->end_date->toDateString())->toBe('2027-01-26');
});

test('writing days replaces whatever days a listing had', function () {
    $listing = Listing::factory()->onDays('2026-11-05', '2026-11-12')->create();

    (new ListingDays)->writeDays($listing, ['2026-11-03', '2026-11-17']);

    expect(savedDays($listing))->toBe(['2026-11-03', '2026-11-17']);
});

test('writing no days clears them', function () {
    $listing = Listing::factory()->onDays('2026-11-05', '2026-11-12')->create();

    (new ListingDays)->writeDays($listing, []);

    expect(savedDays($listing))->toBe([]);
});
