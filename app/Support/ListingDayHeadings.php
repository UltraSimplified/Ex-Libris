<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * What a listings page needs to head its listings with the days they were
 * ordered by: the server's own today, so that TODAY over the list means
 * what it meant to the query whatever the reader's clock says; the day
 * the order counted from; and whether to head the list at all, which a
 * page about a single day has no need of.
 */
final class ListingDayHeadings
{
    /**
     * @return array{currentDay: string, firstDay: string, dayHeadings: bool}
     */
    public static function for(CarbonInterface $firstDay, bool $headed = true): array
    {
        return [
            'currentDay' => today()->toDateString(),
            'firstDay' => $firstDay->toDateString(),
            'dayHeadings' => $headed,
        ];
    }
}
