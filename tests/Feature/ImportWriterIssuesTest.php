<?php

use App\Models\Issue;
use App\Models\User;

/**
 * The list read out of the printed magazine gives each writer the issues
 * they have a byline in. It runs against the live database, so a dry run
 * must leave everything as it was, and a real run must only ever add: links
 * made by hand in the Office stay, and running it twice changes nothing.
 */
afterEach(function () {
    @unlink(writerIssuesFile());
});

function writerIssuesFile(): string
{
    return sys_get_temp_dir().'/writer-issues-'.getmypid().'.json';
}

/**
 * Write the list as the bylines build does, and name the command that reads it.
 *
 * @param  list<array<string, mixed>>  $entries
 */
function writerIssuesCommand(array $entries, string $options = '--commit'): string
{
    file_put_contents(writerIssuesFile(), json_encode($entries));

    return trim('writers:import-issues '.writerIssuesFile().' '.$options);
}

/**
 * @return list<int>
 */
function issuesOf(User $writer): array
{
    return $writer->issues()->reorder('issue_number')->pluck('issue_number')->all();
}

beforeEach(function () {
    foreach ([20, 39, 63, 75] as $number) {
        Issue::factory()->numbered($number)->create();
    }
});

test('a dry run reports what it would do and writes nothing', function () {
    $writer = User::factory()->writer()->create(['name' => 'Matt Shin']);

    $this->artisan(writerIssuesCommand([
        ['name' => 'Matt Shinn', 'formerly' => 'Matt Shin', 'issues' => [39, 63]],
        ['name' => 'Chris Head', 'issues' => [20]],
    ], ''))
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('Matt Shin → Matt Shinn')
        ->expectsOutputToContain('Chris Head')
        ->assertSuccessful();

    expect($writer->fresh()->name)->toBe('Matt Shin')
        ->and(issuesOf($writer))->toBe([])
        ->and(User::where('name', 'Chris Head')->exists())->toBeFalse();
});

test('a writer is renamed to the name they are filed under and given their issues', function () {
    $writer = User::factory()->writer()->create(['name' => 'John Seagrave']);

    $this->artisan(writerIssuesCommand([
        ['name' => 'Jon Seagrave', 'formerly' => 'John Seagrave', 'issues' => [39, 63]],
    ]))->assertSuccessful();

    expect($writer->fresh()->name)->toBe('Jon Seagrave')
        ->and(issuesOf($writer))->toBe([39, 63]);
});

test('a writer the site has never had is added as a writer', function () {
    $this->artisan(writerIssuesCommand([
        ['name' => 'Chris Head', 'issues' => [20, 75]],
    ]))->assertSuccessful();

    $writer = User::where('name', 'Chris Head')->sole();

    expect($writer->is_writer)->toBeTrue()
        ->and($writer->email)->toBeNull()
        ->and(issuesOf($writer))->toBe([20, 75]);
});

test('issues linked by hand are kept, and a second run adds nothing', function () {
    $writer = User::factory()->writer()->create(['name' => 'Paul Harper']);
    $writer->issues()->attach(Issue::where('issue_number', 75)->value('id'));

    $command = writerIssuesCommand([['name' => 'Paul Harper', 'issues' => [20, 63]]]);

    $this->artisan($command)->assertSuccessful();
    $this->artisan($command)->assertSuccessful();

    expect(issuesOf($writer))->toBe([20, 63, 75])
        ->and($writer->issues()->count())->toBe(3)
        ->and(User::where('name', 'Paul Harper')->count())->toBe(1);
});

/**
 * Issues are for writers alone, as in the Office: an account that is not a
 * writer is left as it is, not made one by the import.
 */
test('an account that is not a writer is skipped, not promoted', function () {
    $editor = User::factory()->create(['name' => 'Alex Hobbis']);

    $this->artisan(writerIssuesCommand([
        ['name' => 'Alex Hobbis', 'issues' => [20]],
    ]))
        ->expectsOutputToContain('is not a writer')
        ->assertSuccessful();

    expect($editor->fresh()->is_writer)->toBeFalse()
        ->and(issuesOf($editor))->toBe([])
        ->and(User::where('name', 'Alex Hobbis')->count())->toBe(1);
});

test('issue numbers the site does not have are reported and left out', function () {
    $writer = User::factory()->writer()->create(['name' => 'Tom Berry']);

    $this->artisan(writerIssuesCommand([
        ['name' => 'Tom Berry', 'issues' => [42, 63]],
    ]))
        ->expectsOutputToContain('#42 (Tom Berry)')
        ->assertSuccessful();

    expect(issuesOf($writer))->toBe([63]);
});
