<?php

use App\Models\Listing;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

function calendarFileFor(Listing $listing): TestResponse
{
    return test()->get(route('calendar.listing', $listing));
}

/**
 * The file's lines as a calendar app reads them, with long ones put back
 * together.
 *
 * @return list<string>
 */
function unfoldedLines(string $file): array
{
    return explode("\r\n", str_replace("\r\n ", '', $file));
}

test('a listing comes as a calendar file, with no session or cookie', function () {
    $listing = Listing::factory()->create(['slug' => 'village-fete']);

    $response = calendarFileFor($listing)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertHeader('Content-Disposition', 'inline; filename="village-fete.ics"');

    expect($response->headers->getCookies())->toBeEmpty();
});

test('the file holds one event, with a UID that stays with the listing', function () {
    $listing = Listing::factory()->create();

    $lines = unfoldedLines(calendarFileFor($listing)->getContent());

    expect($lines)->toContain('BEGIN:VCALENDAR', 'BEGIN:VEVENT', 'END:VEVENT', 'END:VCALENDAR', 'UID:listing-'.$listing->id.'@goodonpaper.info')
        ->and(array_count_values($lines)['BEGIN:VEVENT'])->toBe(1);
});

/*
 * The standard takes an all-day event's end as the day after its last, and
 * an end on or before the start as no run at all.
 */
test('an event is all-day and ends the day after its last', function (?string $endDate, string $expectedEnd) {
    $listing = Listing::factory()->create(['start_date' => '2030-03-29', 'end_date' => $endDate]);

    expect(unfoldedLines(calendarFileFor($listing)->getContent()))
        ->toContain('DTSTART;VALUE=DATE:20300329', 'DTEND;VALUE=DATE:'.$expectedEnd);
})->with([
    'a single day' => [null, '20300330'],
    'a run, across the month and the clocks going forward' => ['2030-04-02', '20300403'],
    'an end on the same day' => ['2030-03-29', '20300330'],
    'an end before the start' => ['2030-03-20', '20300330'],
]);

test('the location and notes say what is known', function () {
    $venue = Venue::factory()->create(['name' => 'The Hall', 'address' => '1 High Street', 'town' => 'Stroud', 'postcode' => 'GL5 1AA']);
    $listing = Listing::factory()->create([
        'venue_id' => $venue->id,
        'title' => 'Village Fete',
        'event_time_description' => 'Doors 7pm',
        'additional_date_information' => 'Thursdays only',
        'ticket_price' => 'Free',
    ]);
    $url = route('Listing', ['id' => $listing->id, 'slug' => $listing->slug]);

    expect(unfoldedLines(calendarFileFor($listing)->getContent()))->toContain(
        'SUMMARY:Village Fete',
        'LOCATION:The Hall\, 1 High Street\, Stroud\, GL5 1AA',
        'DESCRIPTION:Time: Doors 7pm\nDates: Thursdays only\nPrice: Free\n'.$url,
        'URL:'.$url,
    );
});

test('a listing with no venue, time or price leaves out what is missing', function () {
    $listing = Listing::factory()->create([
        'venue_id' => null,
        'event_time_description' => null,
        'additional_date_information' => null,
        'ticket_price' => null,
    ]);
    $url = route('Listing', ['id' => $listing->id, 'slug' => $listing->slug]);

    $lines = unfoldedLines(calendarFileFor($listing)->assertOk()->getContent());

    expect($lines)->toContain('DESCRIPTION:'.$url)
        ->and(implode("\n", $lines))->not->toContain('LOCATION:');
});

test('text is escaped as the standard wants it', function () {
    $listing = Listing::factory()->create([
        'title' => 'Bands, beers; back\\slash',
        'event_time_description' => "Doors 7pm\nStart 8pm",
    ]);

    $lines = unfoldedLines(calendarFileFor($listing)->getContent());

    expect($lines)->toContain('SUMMARY:Bands\, beers\; back\\\\slash');
    expect(implode("\n", $lines))->toContain('DESCRIPTION:Time: Doors 7pm\nStart 8pm\n');
});

test('long lines are folded in bytes without splitting a character', function () {
    $title = str_repeat('Café 🎸 ', 30);
    $listing = Listing::factory()->create(['title' => $title]);

    $file = calendarFileFor($listing)->getContent();

    foreach (explode("\r\n", $file) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75)
            ->and(mb_check_encoding($line, 'UTF-8'))->toBeTrue();
    }

    expect(unfoldedLines($file))->toContain('SUMMARY:'.$title);
});

test('the stamp is in UTC, whatever the clocks are doing here', function () {
    Carbon::setTestNow('2030-07-01 12:00:00');

    $listing = Listing::factory()->create();

    expect(unfoldedLines(calendarFileFor($listing)->getContent()))->toContain('DTSTAMP:20300701T110000Z');
});

test('a listing that is not there is a plain not found', function (string $path) {
    $response = $this->get($path)->assertNotFound();

    expect($response->getContent())->toBe('');
})->with([
    'no such listing' => '/calendar/listings/999999.ics',
    'not a listing at all' => '/calendar/listings/nonsense.ics',
]);
