<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Venue;
use App\Nova\Actions\ExportListings;
use App\Services\Listings\ListingCsvExport;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Http\Requests\NovaRequest;

function csvOf(string $from, string $to): string
{
    $handle = fopen('php://memory', 'w+b');
    app(ListingCsvExport::class)->writeTo($handle, $from, $to);
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return $csv;
}

test('the header matches the column order the magazine expects', function () {
    expect(explode("\n", csvOf('2026-09-01', '2026-09-30'))[0])
        ->toBe('category,venue,dates,event_time_description,title,body,ticket_price,event_link,start_date,end_date');
});

test('the dates column is written out as the listings pages write it', function (string $start, ?string $end, ?string $additional, string $expected) {
    Listing::factory()->create([
        'start_date' => $start,
        'end_date' => $end,
        'additional_date_information' => $additional,
    ]);

    expect(str_getcsv(explode("\n", trim(csvOf('2026-10-01', '2027-01-31')))[1])[2])->toBe($expected);
})->with([
    'a single day' => ['2026-10-22', null, null, 'THURS 22 OCT'],
    'two days' => ['2026-10-22', '2026-10-23', null, 'THURS 22 - FRI 23 OCT'],
    'across months' => ['2026-10-22', '2026-11-26', null, 'THURS 22 OCT - THURS 26 NOV'],
    'across years' => ['2026-12-22', '2027-01-21', null, 'TUES 22 DEC 26 - THURS 21 JAN 27'],
    'with additional date information' => ['2026-10-22', '2026-11-26', 'Thursdays only', 'THURS 22 OCT - THURS 26 NOV (Thursdays only)'],
    'with blank additional date information' => ['2026-10-22', null, '  ', 'THURS 22 OCT'],
]);

test('rows are grouped by category then ordered by date', function () {
    $music = Category::factory()->create(['name' => 'Music']);
    $arts = Category::factory()->create(['name' => 'Arts + Crafts']);

    Listing::factory()->create(['title' => 'Late Gig', 'category_id' => $music->id, 'start_date' => '2026-09-20']);
    Listing::factory()->create(['title' => 'Early Gig', 'category_id' => $music->id, 'start_date' => '2026-09-02']);
    Listing::factory()->create(['title' => 'A Show', 'category_id' => $arts->id, 'start_date' => '2026-09-15']);

    $titles = collect(explode("\n", trim(csvOf('2026-09-01', '2026-09-30'))))
        ->skip(1)
        ->map(fn (string $line) => str_getcsv($line)[4])
        ->values()
        ->all();

    expect($titles)->toBe(['A Show', 'Early Gig', 'Late Gig']);
});

test('a listing with no venue exports an empty venue rather than the display fallback', function () {
    Listing::factory()->create([
        'title' => 'No Venue',
        'venue_id' => null,
        'start_date' => '2026-09-10',
    ]);

    $row = str_getcsv(explode("\n", trim(csvOf('2026-09-01', '2026-09-30')))[1]);

    expect($row[1])->toBe('')
        ->and($row[1])->not->toBe('Venue to be confirmed');
});

test('a boosted listing exports neither its premium flag nor its thumbnail', function () {
    Listing::factory()->create([
        'title' => 'With Art',
        'body' => 'The body',
        'boosted' => true,
        'thumbnail' => 'abc123.webp',
        'start_date' => '2026-09-10',
    ]);

    $line = explode("\n", trim(csvOf('2026-09-01', '2026-09-30')))[1];

    expect($line)->not->toContain('abc123.webp')
        ->and(str_getcsv($line)[5])->toBe('The body');
});

test('only fields that need quoting are quoted', function () {
    Listing::factory()->create([
        'title' => 'Plain Title',
        'body' => 'One, two and three',
        'start_date' => '2026-09-10',
    ]);

    $line = explode("\n", trim(csvOf('2026-09-01', '2026-09-30')))[1];

    // A space alone must not trigger quoting, a comma must.
    expect($line)->toContain('Plain Title,')
        ->and($line)->toContain('"One, two and three"');
});

test('a quote inside a field is doubled', function () {
    Listing::factory()->create([
        'title' => 'The "Big" Night',
        'start_date' => '2026-09-10',
    ]);

    $line = explode("\n", trim(csvOf('2026-09-01', '2026-09-30')))[1];

    expect($line)->toContain('"The ""Big"" Night"')
        ->and(str_getcsv($line)[4])->toBe('The "Big" Night');
});

test('the range includes runs that overlap it but not those outside', function () {
    $spanning = Listing::factory()->create(['title' => 'Spanning', 'start_date' => '2026-08-01', 'end_date' => '2026-09-15']);
    $inside = Listing::factory()->create(['title' => 'Inside', 'start_date' => '2026-09-10']);
    $before = Listing::factory()->create(['title' => 'Before', 'start_date' => '2026-08-01', 'end_date' => '2026-08-20']);
    $after = Listing::factory()->create(['title' => 'After', 'start_date' => '2026-10-05']);

    $csv = csvOf('2026-09-01', '2026-09-30');

    expect($csv)->toContain('Spanning', 'Inside')
        ->and($csv)->not->toContain('Before')
        ->and($csv)->not->toContain('After');
});

test('the filename follows the house style', function () {
    Listing::factory()->create(['start_date' => '2026-09-10']);

    expect(app(ListingCsvExport::class)->filename('2026-09-01', '2026-09-30'))
        ->toBe('SEP26_Listings.csv');
});

test('a month of nothing but carry-overs is still named for itself', function () {
    // The state of the data in the days after an issue closes: October's own
    // listings are not in yet, and every row is a September run still going.
    Listing::factory()->count(4)->create(['start_date' => '2026-09-05', 'end_date' => '2026-10-20']);

    expect(app(ListingCsvExport::class)->filename('2026-10-01', '2026-10-31'))
        ->toBe('OCT26_Listings.csv');
});

test('the filename names the month most of the listings fall in', function () {
    // A range nudged back into August, where the bulk of the file is still
    // September: the first date of the range would name it AUG26.
    Listing::factory()->create(['start_date' => '2026-08-28']);
    Listing::factory()->count(3)->create(['start_date' => '2026-09-05']);

    expect(app(ListingCsvExport::class)->filename('2026-08-25', '2026-09-30'))
        ->toBe('SEP26_Listings.csv');
});

test('long runners do not steal the filename from the month being laid out', function () {
    Listing::factory()->count(3)->create(['start_date' => '2026-04-01', 'end_date' => '2026-12-31']);
    Listing::factory()->count(2)->create(['start_date' => '2026-10-08']);

    expect(app(ListingCsvExport::class)->filename('2026-10-01', '2026-10-31'))
        ->toBe('OCT26_Listings.csv');
});

test('an empty range is named after the range itself', function () {
    expect(app(ListingCsvExport::class)->filename('2026-10-01', '2026-10-31'))
        ->toBe('OCT26_Listings.csv');
});

test('a tie is settled by the earlier month', function () {
    Listing::factory()->create(['start_date' => '2026-09-20']);
    Listing::factory()->create(['start_date' => '2026-10-02']);

    expect(app(ListingCsvExport::class)->filename('2026-09-15', '2026-10-15'))
        ->toBe('SEP26_Listings.csv');
});

test('the action offers next month by default', function () {
    // Nova only fills a default on a form request, and an action request is
    // recognised by the third URI segment.
    $request = NovaRequest::create('/nova-api/listings/actions');

    $this->travelTo('2026-09-13');

    $fields = collect((new ExportListings)->fields($request))->keyBy(fn ($field) => $field->attribute);

    expect($fields['from']->resolveDefaultValue($request))->toBe('2026-10-01')
        ->and($fields['to']->resolveDefaultValue($request))->toBe('2026-10-31');
});

test('the default range rolls over the end of the year', function () {
    $request = NovaRequest::create('/nova-api/listings/actions');

    $this->travelTo('2026-12-13');

    $fields = collect((new ExportListings)->fields($request))->keyBy(fn ($field) => $field->attribute);

    expect($fields['from']->resolveDefaultValue($request))->toBe('2027-01-01')
        ->and($fields['to']->resolveDefaultValue($request))->toBe('2027-01-31');
});

test('the default range is unaffected by a short month', function () {
    // now()->addMonth() from 31 January lands in March; addMonthNoOverflow
    // keeps it in February.
    $request = NovaRequest::create('/nova-api/listings/actions');

    $this->travelTo('2027-01-31');

    $fields = collect((new ExportListings)->fields($request))->keyBy(fn ($field) => $field->attribute);

    expect($fields['from']->resolveDefaultValue($request))->toBe('2027-02-01')
        ->and($fields['to']->resolveDefaultValue($request))->toBe('2027-02-28');
});

test('the export route refuses an unsigned request', function () {
    $this->get(route('exports.listings', ['from' => '2026-09-01', 'to' => '2026-09-30']))
        ->assertForbidden();
});

test('a signed link downloads the csv', function () {
    Venue::factory()->create();
    Listing::factory()->create(['title' => 'Signed Download', 'start_date' => '2026-09-10']);

    $url = URL::temporarySignedRoute('exports.listings', now()->addMinutes(10), [
        'from' => '2026-09-01',
        'to' => '2026-09-30',
    ]);

    $response = $this->get($url)->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('SEP26_Listings.csv');
    expect($response->streamedContent())->toContain('Signed Download');
});

test('an end date before the start date is rejected', function () {
    $url = URL::temporarySignedRoute('exports.listings', now()->addMinutes(10), [
        'from' => '2026-09-30',
        'to' => '2026-09-01',
    ]);

    $this->get($url)->assertSessionHasErrors('to');
});
