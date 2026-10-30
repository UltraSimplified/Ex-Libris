<?php

use App\Models\Listing;

/**
 * The date over a listing is worked out from its first and last days, and
 * only says as much as it needs to: the month once when the run stays
 * inside it, and the year only when it crosses into the next. Anything
 * the two dates cannot say follows them in brackets.
 */
test('a listing page writes its dates out from its start and end', function (string $start, ?string $end, ?string $additional, string $expected) {
    $listing = Listing::factory()->create([
        'start_date' => $start,
        'end_date' => $end,
        'additional_date_information' => $additional,
    ]);

    visit(route('Listing', [$listing->id, $listing->slug], absolute: false))
        ->assertSee($expected);
})->with([
    'a single day' => ['2026-10-22', null, null, 'THURS 22 OCT'],
    'two days' => ['2026-10-22', '2026-10-23', null, 'THURS 22 - FRI 23 OCT'],
    'across months' => ['2026-10-22', '2026-11-26', null, 'THURS 22 OCT - THURS 26 NOV'],
    'across years' => ['2026-12-22', '2027-01-21', null, 'TUES 22 DEC 26 - THURS 21 JAN 27'],
    'with additional date information' => ['2026-10-22', '2026-11-26', 'THURSDAYS ONLY', 'THURS 22 OCT - THURS 26 NOV (THURSDAYS ONLY)'],
    'with blank additional date information' => ['2026-10-22', null, '  ', 'THURS 22 OCT'],
]);
