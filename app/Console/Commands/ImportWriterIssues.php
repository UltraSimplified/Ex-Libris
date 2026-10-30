<?php

namespace App\Console\Commands;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Links writers to the back issues they have a byline in, from the list
 * read out of the printed magazine.
 *
 * Each entry names a writer as they are to be filed and the issue numbers
 * they are in print in. A writer is found by that name, or by the name the
 * site knew them by ("formerly"), in which case they are renamed; one the
 * site has never had is added as a writer. Issues are only ever added to a
 * writer, never taken away, so links made by hand in the Office are kept,
 * and a second run adds nothing.
 *
 * Runs as a dry run unless --commit is passed, because the configured
 * database is the live one.
 */
class ImportWriterIssues extends Command
{
    protected $signature = 'writers:import-issues
        {file : Path to the JSON list of writers and the issue numbers they are in print in}
        {--commit : Actually write to the database}';

    protected $description = 'Link writers to the issues they are in print in, adding and renaming writers as the list says';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        /** @var list<array{name: string, formerly?: string|null, issues: list<int>}> $entries */
        $entries = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $issueIds = Issue::query()->pluck('id', 'issue_number');

        $outcome = ['existing' => 0, 'renamed' => [], 'created' => [], 'skipped' => [], 'linked' => 0, 'already' => 0, 'unknown' => []];

        $import = function () use ($entries, $issueIds, &$outcome): void {
            foreach ($entries as $entry) {
                $name = trim($entry['name']);
                $formerly = isset($entry['formerly']) ? trim($entry['formerly']) : null;
                $numbers = array_values(array_unique(array_map('intval', $entry['issues'])));

                $writer = $this->find($name, $formerly, $outcome['skipped']);

                if ($writer === false) {
                    continue;
                }

                if ($writer === null) {
                    $outcome['created'][] = $name;
                    $writer = $this->option('commit') ? $this->createWriter($name) : null;
                } elseif ($writer->name !== $name) {
                    $outcome['renamed'][] = "{$writer->name} → {$name} (#{$writer->id})";

                    if ($this->option('commit')) {
                        $writer->name = $name;
                        $writer->save();
                    }
                } else {
                    $outcome['existing']++;
                }

                $known = array_values(array_filter($numbers, fn (int $number): bool => $issueIds->has($number)));

                foreach (array_diff($numbers, $known) as $number) {
                    $outcome['unknown'][] = "#{$number} ({$name})";
                }

                $linked = $writer?->issues()->pluck('issue_number')->all() ?? [];
                $toLink = array_values(array_diff($known, $linked));

                $outcome['already'] += count($known) - count($toLink);
                $outcome['linked'] += count($toLink);

                if ($this->option('commit') && $toLink !== []) {
                    $writer->issues()->syncWithoutDetaching(array_map(fn (int $number): int => $issueIds[$number], $toLink));
                }
            }
        };

        $this->option('commit') ? DB::transaction($import) : $import();

        $this->report(count($entries), $outcome);

        return self::SUCCESS;
    }

    /**
     * The writer an entry means: found by the name to file them under, or
     * else by the name the site had for them. Null when the site has neither,
     * and false (with the reason noted) when the entry cannot be applied.
     *
     * @param  list<string>  $skipped
     */
    private function find(string $name, ?string $formerly, array &$skipped): User|false|null
    {
        foreach (array_filter([$name, $formerly]) as $candidate) {
            $matches = User::query()->where('name', $candidate)->get();

            if ($matches->count() > 1) {
                $skipped[] = "{$name}: more than one account is called {$candidate}";

                return false;
            }

            $user = $matches->first();

            if ($user === null) {
                continue;
            }

            if (! $user->is_writer) {
                // Issues are for writers alone, as in the Office; an account
                // is made a writer there, deliberately, not by an import.
                $skipped[] = "{$name}: #{$user->id} {$user->name} is not a writer";

                return false;
            }

            return $user;
        }

        return null;
    }

    /**
     * Add a writer the site has never had, by name alone, as the Office does.
     */
    private function createWriter(string $name): User
    {
        $writer = new User(['name' => $name]);
        $writer->is_writer = true;
        $writer->save();

        return $writer;
    }

    /**
     * @param  array{existing: int, renamed: list<string>, created: list<string>, skipped: list<string>, linked: int, already: int, unknown: list<string>}  $outcome
     */
    private function report(int $total, array $outcome): void
    {
        $mode = $this->option('commit') ? 'COMMITTED' : 'DRY RUN — nothing written';

        $this->newLine();
        $this->line("<info>{$mode}</info>");
        $this->table(['', 'count'], [
            ['writers in file', $total],
            ['already on the site', $outcome['existing']],
            ['to rename', count($outcome['renamed'])],
            ['to add', count($outcome['created'])],
            ['skipped', count($outcome['skipped'])],
            ['issue links to add', $outcome['linked']],
            ['issue links already there', $outcome['already']],
            ['issue numbers not on the site', count($outcome['unknown'])],
        ]);

        foreach ([
            'Renamed:' => $outcome['renamed'],
            'Added as writers:' => $outcome['created'],
            'Skipped:' => $outcome['skipped'],
            'Issue numbers not on the site:' => $outcome['unknown'],
        ] as $heading => $lines) {
            if ($lines === []) {
                continue;
            }

            $this->newLine();
            $this->line("<comment>{$heading}</comment>");

            foreach ($lines as $line) {
                $this->line("  {$line}");
            }
        }
    }
}
