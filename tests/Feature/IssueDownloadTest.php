<?php

use App\Models\Issue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('do');
});

/**
 * The name a download is saved under: the header's UTF-8 filename, which
 * keeps the dash its plain-ASCII fallback has to drop.
 */
function downloadedAs(TestResponse $response): string
{
    preg_match("/filename\\*=utf-8''([^;]+)/i", (string) $response->headers->get('content-disposition'), $match);

    return rawurldecode($match[1] ?? '');
}

test('an issue downloads under a name that says which issue it is', function () {
    $issue = Issue::factory()->create(['issue_number' => 41, 'month_year' => 'August 2018', 'pdf' => 'issues/41.pdf']);
    Storage::disk('do')->put('issues/41.pdf', '%PDF-1.4 the issue');

    $response = $this->get("/download/{$issue->id}")->assertOk();

    expect(downloadedAs($response))->toBe('GOP — Issue #41 (August 2018).pdf');
});

test('an issue put up as a picture downloads as the picture it is', function () {
    $issue = Issue::factory()->create(['issue_number' => 35, 'month_year' => 'February 2018', 'pdf' => 'issues/35.JPG']);
    Storage::disk('do')->put('issues/35.JPG', 'a photograph of the issue');

    $response = $this->get("/download/{$issue->id}")->assertOk();

    expect(downloadedAs($response))->toBe('GOP — Issue #35 (February 2018).jpg');
});

test('an issue with no file, or whose file has gone, is not found rather than an error', function (?string $pdf) {
    $issue = Issue::factory()->create(['pdf' => $pdf]);

    $this->get("/download/{$issue->id}")->assertNotFound();
})->with([
    'no file put up' => [null],
    'an empty path' => [''],
    'a file gone from the disk' => ['issues/gone.pdf'],
]);

test('an issue that does not exist is not found rather than an error', function (string $id) {
    $this->get("/download/{$id}")->assertNotFound();
})->with(['an unknown number' => ['999999'], 'not a number' => ['latest']]);
