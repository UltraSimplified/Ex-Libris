<?php

namespace App\Services\Listings;

use App\Models\Listing;
use App\Support\ListingDates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the monthly listings CSV handed to the magazine's designers.
 *
 * The column order is the file's contract, so it is declared once here
 * rather than being implied by the order of a query's select.
 */
final class ListingCsvExport
{
    /**
     * @var array<int, string>
     */
    public const COLUMNS = [
        'category',
        'venue',
        'dates',
        'event_time_description',
        'title',
        'body',
        'ticket_price',
        'event_link',
        'start_date',
        'end_date',
    ];

    /**
     * Listings running at any point in the range, as CSV rows.
     *
     * Uses the same overlap test as the public site, so an exhibition that
     * opened before the range and is still running appears in it.
     *
     * @return Collection<int, array<int, string|int>>
     */
    public function rows(mixed $from, mixed $to): Collection
    {
        return $this->query($from, $to)->get()->map(fn (Listing $listing): array => [
            $listing->category?->name ?? '',
            // The venue relation falls back to "Venue to be confirmed" for
            // display; the export wants the column genuinely empty.
            $listing->venue_id ? ($listing->venue->name ?? '') : '',
            ListingDates::for($listing),
            (string) $listing->event_time_description,
            (string) $listing->title,
            (string) $listing->body,
            (string) $listing->ticket_price,
            (string) $listing->event_link,
            $listing->start_date?->format('Y-m-d') ?? '',
            $listing->end_date?->format('Y-m-d') ?? '',
        ]);
    }

    /**
     * Write the export, header included, to an open stream.
     *
     * Rows are formatted here rather than with fputcsv, which quotes any
     * field containing a space. The magazine's file quotes only what has to
     * be quoted, and matching it keeps diffs between months readable.
     *
     * @param  resource  $handle
     */
    public function writeTo($handle, mixed $from, mixed $to): void
    {
        fwrite($handle, $this->line(self::COLUMNS));

        foreach ($this->rows($from, $to) as $row) {
            fwrite($handle, $this->line($row));
        }
    }

    /**
     * Format one CSV record, quoting only the fields that require it.
     *
     * @param  array<int, string|int>  $fields
     */
    private function line(array $fields): string
    {
        $escaped = array_map(function (string|int $field): string {
            $value = (string) $field;

            return preg_match('/[",\r\n]/', $value) === 1
                ? '"'.str_replace('"', '""', $value).'"'
                : $value;
        }, $fields);

        return implode(',', $escaped)."\n";
    }

    /**
     * A filename in the house style, e.g. SEP26_Listings.csv.
     *
     * Named for the month most of the listings start in rather than for the
     * start of the range. The range is widened by a day or two often enough
     * that its first date is a poor guide to which issue the file is for.
     */
    public function filename(mixed $from, mixed $to): string
    {
        $month = $this->dominantMonth($from, $to);

        return strtoupper($month->format('M')).$month->format('y').'_Listings.csv';
    }

    /**
     * The month most of the listings in the range fall in.
     *
     * A listing counts towards the month its run *enters the range*, not the
     * month it originally opened in. An exhibition that began in April and is
     * still running belongs to the issue being laid out, so exporting October
     * must not name the file after April, or after whichever earlier month
     * happens to have the most carry-overs.
     *
     * Ties go to the earlier month, and an empty range falls back to the
     * month the range itself starts in.
     */
    private function dominantMonth(mixed $from, mixed $to): Carbon
    {
        $rangeStart = Carbon::parse($from)->startOfDay();

        $months = $this->query($from, $to)
            ->reorder()
            ->pluck('listings.start_date')
            ->filter()
            ->map(fn ($date): string => Carbon::parse($date)->max($rangeStart)->format('Y-m'))
            ->countBy();

        if ($months->isEmpty()) {
            return Carbon::parse($from)->startOfMonth();
        }

        $most = $months->max();

        $month = $months->filter(fn (int $count): bool => $count === $most)
            ->keys()
            ->sort()
            ->first();

        return Carbon::parse($month.'-01');
    }

    /**
     * Listings in the range, grouped by category and then in date order.
     *
     * Category is sorted by name rather than id, since the id order is the
     * order the categories happened to be created in.
     */
    private function query(mixed $from, mixed $to)
    {
        return Listing::query()
            ->with(['venue', 'category'])
            ->runningBetween($from, $to)
            ->leftJoin('categories', 'categories.id', '=', 'listings.category_id')
            ->orderBy('categories.name')
            ->orderBy('listings.start_date')
            ->orderBy('listings.title')
            ->select('listings.*');
    }
}
