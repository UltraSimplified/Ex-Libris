<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\ListingDay;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Listings as an iCalendar file (RFC 5545), the one format every calendar
 * app understands.
 *
 * Every event is all-day. A listing's time is free text - "Doors 7pm, Start
 * 8pm", "Times vary" - so it goes in the notes rather than being guessed at:
 * a wrong time in somebody's calendar is worse than none.
 */
class ListingCalendar
{
    /**
     * The longest a line may be, in bytes, before it has to be folded.
     */
    private const LINE_LIMIT = 75;

    public static function for(Listing ...$listings): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Good on Paper//Listings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            ...array_merge(...array_map(self::events(...), $listings)),
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * A listing's events, by their lines: one spanning its run, or for a
     * listing on specific days one on each of them, so the pub quiz every
     * other Tuesday does not fill six weeks of somebody's diary.
     *
     * Each UID stays the same for the life of the listing, so a calendar
     * app can tell it is the same event: the listing's own for a run, the
     * listing's and the day's for a specific day.
     *
     * @return list<string>
     */
    private static function events(Listing $listing): array
    {
        if ($listing->days->isEmpty()) {
            $start = $listing->start_date;
            $lastDay = $listing->end_date?->greaterThan($start) ? $listing->end_date : $start;

            return self::event($listing, 'listing-'.$listing->id, $start, $lastDay);
        }

        return array_merge(...$listing->days->map(
            fn (ListingDay $day): array => self::event($listing, 'listing-'.$listing->id.'-'.$day->day->format('Ymd'), $day->day, $day->day)
        )->all());
    }

    /**
     * One all-day event, by its lines. The standard takes an all-day
     * event's end as the day after its last.
     *
     * @return list<string>
     */
    private static function event(Listing $listing, string $uid, CarbonInterface $firstDay, CarbonInterface $lastDay): array
    {
        $url = route('Listing', ['id' => $listing->id, 'slug' => $listing->slug]);

        return array_values(array_filter([
            'BEGIN:VEVENT',
            'UID:'.$uid.'@goodonpaper.info',
            'DTSTAMP:'.($listing->updated_at ?? now())->copy()->utc()->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$firstDay->format('Ymd'),
            'DTEND;VALUE=DATE:'.$lastDay->copy()->addDay()->format('Ymd'),
            'SUMMARY:'.self::escape((string) $listing->title),
            self::location($listing),
            'DESCRIPTION:'.self::escape(self::description($listing, $url)),
            'URL:'.$url,
            'END:VEVENT',
        ]));
    }

    /**
     * Where it is, as far as is known. A listing whose venue has yet to be
     * confirmed has nowhere to say.
     */
    private static function location(Listing $listing): ?string
    {
        $venue = $listing->venue;

        if (! $venue->exists) {
            return null;
        }

        $parts = array_filter(
            [$venue->name, $venue->address, $venue->town, $venue->postcode],
            fn (?string $part): bool => filled($part),
        );

        return 'LOCATION:'.self::escape(Str::squish(implode(', ', $parts)));
    }

    /**
     * What the two dates cannot say, the price and the way back to the
     * listing, a line each.
     */
    private static function description(Listing $listing, string $url): string
    {
        $details = [
            'Time' => $listing->event_time_description,
            'Dates' => $listing->additional_date_information,
            'Price' => $listing->ticket_price,
        ];

        $lines = [];

        foreach ($details as $label => $detail) {
            if (filled($detail)) {
                $lines[] = $label.': '.trim($detail);
            }
        }

        return implode("\n", [...$lines, $url]);
    }

    /**
     * Text as the standard wants it. The backslash goes first, or it would
     * catch the ones this puts in.
     */
    private static function escape(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $text,
        );
    }

    /**
     * Breaks a long line into lines of no more than the limit, each after
     * the first opening with a space. Counted in bytes, without splitting a
     * multi-byte character across two lines.
     */
    private static function fold(string $line): string
    {
        $parts = [];
        $limit = self::LINE_LIMIT;

        while (strlen($line) > $limit) {
            $part = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $part;
            $line = substr($line, strlen($part));
            $limit = self::LINE_LIMIT - 1;
        }

        return implode("\r\n ", [...$parts, $line]);
    }
}
