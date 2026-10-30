<?php

namespace App\Support;

use App\Models\Listing;
use Carbon\CarbonInterface;

/**
 * When a listing runs, written out as the listings pages write it:
 *
 *   THURS 22 OCT
 *   THURS 22 - FRI 23 OCT
 *   THURS 22 OCT - THURS 26 NOV
 *   THURS 22 DEC 26 - THURS 21 JAN 27
 *
 * Only a run crossing into another year carries the year, and anything the
 * two dates cannot say follows them in brackets. Kept in step with
 * resources/js/lib/listingDates.ts, which does the same on the page.
 */
class ListingDates
{
    private const WEEKDAYS = ['SUN', 'MON', 'TUES', 'WED', 'THURS', 'FRI', 'SAT'];

    public static function for(Listing $listing): string
    {
        $dates = $listing->start_date ? self::run($listing->start_date, $listing->end_date) : '';
        $additional = trim((string) $listing->additional_date_information);

        if ($additional === '') {
            return $dates;
        }

        return $dates !== '' ? "{$dates} ({$additional})" : "({$additional})";
    }

    private static function run(CarbonInterface $start, ?CarbonInterface $end): string
    {
        if (! $end || $end->lte($start)) {
            return self::weekdayAndDay($start).' '.self::month($start);
        }

        if ($start->year !== $end->year) {
            return self::weekdayAndDay($start).' '.self::month($start).' '.$start->format('y')
                .' - '.self::weekdayAndDay($end).' '.self::month($end).' '.$end->format('y');
        }

        if ($start->month !== $end->month) {
            return self::weekdayAndDay($start).' '.self::month($start)
                .' - '.self::weekdayAndDay($end).' '.self::month($end);
        }

        return self::weekdayAndDay($start).' - '.self::weekdayAndDay($end).' '.self::month($end);
    }

    private static function weekdayAndDay(CarbonInterface $date): string
    {
        return self::WEEKDAYS[$date->dayOfWeek].' '.$date->day;
    }

    private static function month(CarbonInterface $date): string
    {
        return strtoupper($date->format('M'));
    }
}
