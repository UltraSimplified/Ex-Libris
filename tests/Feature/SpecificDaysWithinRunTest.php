<?php

use App\Rules\SpecificDaysWithinRun;
use Illuminate\Support\Facades\Validator;

/**
 * The Office's calendar value, checked against a run, as the Office's form
 * would send them.
 */
function specificDaysErrors(mixed $value, ?string $endDate = '2026-06-20'): array
{
    return Validator::make(
        ['start_date' => '2026-06-01', 'end_date' => $endDate, 'specific_days' => $value],
        ['specific_days' => [new SpecificDaysWithinRun]],
    )->errors()->get('specific_days');
}

test('days within the run pass', function () {
    $value = json_encode(['enabled' => true, 'days' => ['2026-06-01', '2026-06-13', '2026-06-20']]);

    expect(specificDaysErrors($value))->toBe([]);
});

test('anything passes while the box is unticked', function (mixed $value) {
    expect(specificDaysErrors($value, endDate: null))->toBe([]);
})->with([
    'unticked with days left in it' => [json_encode(['enabled' => false, 'days' => ['2027-01-01']])],
    'nothing sent' => [null],
    'not the calendar\'s JSON' => ['every Tuesday'],
]);

test('a ticked box is refused with the message the dashboard gives', function (array $days, ?string $endDate, string $message) {
    $value = json_encode(['enabled' => true, 'days' => $days]);

    expect(specificDaysErrors($value, $endDate))->toBe([$message]);
})->with([
    'no day ticked' => [[], '2026-06-20', 'Choose at least one day, or untick "Set specific days".'],
    'no end date' => [['2026-06-06'], null, 'Add an end date to choose specific days.'],
    'not a date' => [['next Saturday'], '2026-06-20', 'One of the chosen days is not a date.'],
    'a day that does not exist' => [['2026-06-31'], '2026-07-31', 'One of the chosen days is not a date.'],
    'before the start' => [['2026-05-31'], '2026-06-20', 'Each chosen day must fall between the start and end dates.'],
    'after the end' => [['2026-06-21'], '2026-06-20', 'Each chosen day must fall between the start and end dates.'],
]);
