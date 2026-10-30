<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports a month's listings from the parsed magazine PDF.
 *
 * Runs as a dry run unless --commit is passed, because the configured
 * database is the live one. Re-running is safe: a listing already present
 * with the same title and start date is skipped rather than duplicated.
 *
 * A listing somebody added by hand takes precedence over the magazine's
 * version of the same event. Those rarely match exactly - a run that began
 * earlier starts on another day, and a title picks up words - so they are
 * recognised by venue, overlapping days and a title that reads as the same
 * event, and the magazine's copy is left out.
 */
class ImportListings extends Command
{
    protected $signature = 'listings:import
        {file : Path to the JSON produced by the listings parser}
        {--commit : Actually write to the database}
        {--create-venues : Create venues that are not already known}';

    protected $description = 'Import listings from a parsed magazine listings file';

    /**
     * Section headings in the magazine mapped to category ids.
     *
     * @var array<string, int>
     */
    private const CATEGORIES = [
        'ART + CRAFTS' => 1,
        'MUSIC' => 2,
        'TALKS/SPOKEN WORD/COMEDY/LITERATURE' => 3,
        'THEATRE' => 4,
        'FILM' => 5,
        'DANCE' => 6,
        'CIRCUS' => 7,
        'COMMUNITY EVENTS' => 8,
    ];

    /**
     * Venue names written differently in the magazine than in the database.
     *
     * @var array<string, string>
     */
    private const VENUE_ALIASES = [
        'SVA' => 'SVA — Stroud Valley Artspace',
        'Locking Hill Studio' => 'Locking Hill Streaming Studio',
        'Holy Trinity Church' => 'Holy Trinity Church Minchinhampton',
        'Lansdown Gallery' => 'Lansdown Hall',
        'Baptist Hall' => 'The Baptist Church Hall',
        'Gloucestershire Printmaking Cooperative' => 'Gloucestershire Printmaking Co-operative',
        'Imagine Therapeutic Art' => 'Imagine Therapeutic Arts',
        'Jackson’s Art' => 'Jackson Art',
        'Painswick Arts Centre' => 'The Painswick Centre',
        'Victoria Works Studios' => 'Victoria Works Studio',
    ];

    /**
     * Words that carry no weight when two titles are compared.
     *
     * @var list<string>
     */
    private const LITTLE_WORDS = ['the', 'a', 'an', 'at', 'with', 'and', 'of', 'in', 'on', 'for', 'to', 'by', 'presents', 'present'];

    /**
     * The fewest letters a title can have and still count as the same event
     * by sitting inside a longer one, so "Red" is not "Reduced Price Night".
     */
    private const MIN_LETTERS_INSIDE_ANOTHER = 6;

    /**
     * How alike two titles must be, out of 100, to be one title with a typo.
     */
    private const TYPO_LIKENESS = 80;

    /**
     * How much of their words two titles must share, out of 1, to be the same
     * words in another order or with a few added.
     */
    private const WORD_OVERLAP = 0.6;

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        $events = collect(json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));
        $venues = $this->venueLookup();
        $handAdded = $this->handAddedListings($events);

        $created = 0;
        $skipped = [];
        $partlyCovered = [];
        $unmatchedVenues = [];

        $import = function () use ($events, $handAdded, &$venues, &$created, &$skipped, &$partlyCovered, &$unmatchedVenues): void {
            foreach ($events as $event) {
                $title = trim((string) ($event['title'] ?? ''));
                $start = $event['start_date'] ?? null;
                $end = $event['end_date'] ?? null;

                if ($title === '' || $start === null) {
                    $skipped[] = ['no date', $title ?: '(untitled)'];

                    continue;
                }

                $venueName = trim((string) ($event['venue'] ?? ''));
                $venueId = $venues[$this->key(self::VENUE_ALIASES[$venueName] ?? $venueName)] ?? null;

                $twin = $this->handAddedTwin($handAdded, $venueId, $venueName, $title, $start, $end);

                if ($twin !== null) {
                    $skipped[] = ['hand-added listing takes precedence', "{$start}  {$title}  (#{$twin->id} “{$twin->title}”)"];

                    $gap = $this->coverageGap($twin, $title, $start, $end);

                    if ($gap !== null) {
                        $partlyCovered[] = $gap;
                    }

                    continue;
                }

                if ($this->alreadyPresent($title, $start)) {
                    $skipped[] = ['already in database', "{$start}  {$title}"];

                    continue;
                }

                if ($venueId === null && $venueName !== '') {
                    $unmatchedVenues[$venueName] = ($unmatchedVenues[$venueName] ?? 0) + 1;

                    if ($this->option('create-venues') && $this->option('commit')) {
                        // Set directly rather than mass assigning: Venue has no
                        // $fillable, and Nova relies on that model as it stands.
                        $venue = new Venue;
                        $venue->name = $venueName;
                        $venue->save();

                        $venueId = $venue->id;
                        $venues[$this->key($venueName)] = $venueId;
                    }
                }

                $attributes = [
                    'title' => $title,
                    'body' => (string) ($event['body'] ?? ''),
                    'category_id' => self::CATEGORIES[$event['category']] ?? null,
                    'venue_id' => $venueId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'additional_date_information' => $event['dates_raw'] ?? null,
                    'event_time_description' => $event['event_time_description'] ?? null,
                    'ticket_price' => $event['ticket_price'] ?? null,
                    'event_link' => $this->link($event['event_link'] ?? null),
                    'button_text' => 'More information',
                ];

                if ($this->option('commit')) {
                    Listing::create($attributes);
                }

                $created++;
            }
        };

        $this->option('commit') ? DB::transaction($import) : $import();

        $this->report($events->count(), $created, $skipped, $partlyCovered, $unmatchedVenues);

        return self::SUCCESS;
    }

    /**
     * Is a listing with this title already recorded against this start date?
     */
    private function alreadyPresent(string $title, string $start): bool
    {
        return Listing::whereDate('start_date', $start)
            ->get(['title'])
            ->contains(fn (Listing $listing): bool => $this->key($listing->title) === $this->key($title));
    }

    /**
     * Listings somebody added by hand that run on any day the file covers,
     * grouped by venue.
     *
     * Fetched once: a month's file has a few hundred events to compare with
     * a handful of listings entered by hand.
     *
     * @param  Collection<int, array<string, mixed>>  $events
     * @return Collection<int, Collection<int, Listing>>
     */
    private function handAddedListings(Collection $events): Collection
    {
        $from = $events->pluck('start_date')->filter()->min();

        if ($from === null) {
            return collect();
        }

        $to = $events
            ->map(fn (array $event): ?string => $event['end_date'] ?? $event['start_date'] ?? null)
            ->filter()
            ->max();

        return Listing::handAdded()
            ->whereNotNull('venue_id')
            ->runningBetween($from, $to)
            ->get(['id', 'venue_id', 'title', 'start_date', 'end_date'])
            ->groupBy('venue_id');
    }

    /**
     * The hand-added listing that is already this event, if there is one.
     *
     * At the same venue, on some of the same days, under a title that reads
     * as the same event. An exact title and start date is not asked for: the
     * people who add listings themselves start a run on the day they know
     * of, and word a title their own way.
     *
     * @param  Collection<int, Collection<int, Listing>>  $handAdded
     */
    private function handAddedTwin(Collection $handAdded, ?int $venueId, string $venueName, string $title, string $start, ?string $end): ?Listing
    {
        if ($venueId === null) {
            return null;
        }

        $end ??= $start;

        return $handAdded->get($venueId, collect())->first(
            fn (Listing $listing): bool => $listing->start_date->toDateString() <= $end
                && $this->lastDay($listing) >= $start
                && $this->sameEvent($listing->title, $title, $venueName)
        );
    }

    /**
     * What a hand-added listing leaves out of the magazine's days, if anything.
     *
     * The magazine gives a start and an end, so a run of dates such as
     * "Fri 2/Fri 23" is one span, and the listing has to cover all of it.
     */
    private function coverageGap(Listing $twin, string $title, string $start, ?string $end): ?string
    {
        $end ??= $start;
        $first = $twin->start_date->toDateString();
        $last = $this->lastDay($twin);

        if ($first <= $start && $last >= $end) {
            return null;
        }

        return "{$title}: the magazine has {$start} to {$end}, #{$twin->id} has {$first} to {$last}";
    }

    /**
     * The last day a listing runs. A single-day listing has no end date.
     */
    private function lastDay(Listing $listing): string
    {
        return ($listing->end_date ?? $listing->start_date)->toDateString();
    }

    /**
     * Do two titles read as one event?
     *
     * They do when they are the same once case and punctuation are set aside,
     * when one sits inside the other, when they use the same words in another
     * order, or when they are a slip of the keyboard apart.
     */
    private function sameEvent(string $existing, string $incoming, string $venueName): bool
    {
        $existingKey = $this->key($existing);
        $incomingKey = $this->key($incoming);

        if ($existingKey === '' || $incomingKey === '') {
            return false;
        }

        if ($existingKey === $incomingKey) {
            return true;
        }

        $insideAnother = str_contains($existingKey, $incomingKey) || str_contains($incomingKey, $existingKey);

        if ($insideAnother && min(mb_strlen($existingKey), mb_strlen($incomingKey)) >= self::MIN_LETTERS_INSIDE_ANOTHER) {
            return true;
        }

        similar_text($existingKey, $incomingKey, $likeness);

        return $likeness >= self::TYPO_LIKENESS
            || $this->wordOverlap($existing, $incoming, $venueName) >= self::WORD_OVERLAP;
    }

    /**
     * The share of their words two titles have in common, leaving out the
     * venue's own name and the little words, which titles pick up freely
     * ("Fabric Swap at Emergence Commons").
     */
    private function wordOverlap(string $a, string $b, string $venueName): float
    {
        $ignored = [...self::LITTLE_WORDS, ...$this->words($venueName)];

        $wordsA = array_diff($this->words($a), $ignored);
        $wordsB = array_diff($this->words($b), $ignored);
        $either = count(array_unique([...$wordsA, ...$wordsB]));

        return $either === 0 ? 0.0 : count(array_intersect($wordsA, $wordsB)) / $either;
    }

    /**
     * @return list<string>
     */
    private function words(string $value): array
    {
        return Str::of($value)
            ->lower()
            ->replace('&', ' and ')
            ->matchAll('/[\p{L}\p{N}]+/u')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function venueLookup(): array
    {
        return Venue::all(['id', 'name'])
            ->mapWithKeys(fn (Venue $venue): array => [$this->key($venue->name) => $venue->id])
            ->all();
    }

    /**
     * Compare names and titles ignoring case, punctuation and spacing.
     *
     * The magazine and the database disagree on "&" versus "and" and on
     * whether a venue keeps its leading "The", so both are flattened.
     */
    private function key(?string $value): string
    {
        $value = str_replace('&', ' and ', mb_strtolower((string) $value));
        $value = (string) preg_replace('/^\s*the\s+/', '', $value);

        return (string) preg_replace('/[^a-z0-9]+/', '', $value);
    }

    /**
     * Normalise a bare domain from the magazine into a URL.
     */
    private function link(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str_starts_with($value, 'http') ? $value : 'https://'.ltrim($value, '/');
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $skipped
     * @param  array<int, string>  $partlyCovered
     * @param  array<string, int>  $unmatchedVenues
     */
    private function report(int $total, int $created, array $skipped, array $partlyCovered, array $unmatchedVenues): void
    {
        $mode = $this->option('commit') ? 'COMMITTED' : 'DRY RUN — nothing written';

        $this->newLine();
        $this->line("<info>{$mode}</info>");
        $this->table(['', 'count'], [
            ['events in file', $total],
            ['to create', $created],
            ['skipped', count($skipped)],
        ]);

        if ($skipped !== []) {
            $this->line('<comment>Skipped:</comment>');
            foreach ($skipped as [$reason, $what]) {
                $this->line("  [{$reason}] {$what}");
            }
        }

        if ($partlyCovered !== []) {
            $this->newLine();
            $this->line('<comment>Only partly covered by a hand-added listing, so check the days it leaves out:</comment>');
            foreach ($partlyCovered as $gap) {
                $this->line("  {$gap}");
            }
        }

        if ($unmatchedVenues !== []) {
            $this->newLine();
            $verb = $this->option('create-venues') ? 'Created' : 'No venue record (listing left unassigned)';
            $this->line("<comment>{$verb}:</comment>");
            foreach ($unmatchedVenues as $name => $count) {
                $this->line("  {$name} ({$count} listing".($count === 1 ? '' : 's').')');
            }
        }
    }
}
