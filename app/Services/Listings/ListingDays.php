<?php

namespace App\Services\Listings;

use App\Models\Listing;
use App\Models\ListingDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The specific days a listing is on, for a run that is not on every day:
 * a pub quiz every other Tuesday, a festival at weekends.
 *
 * A listing with specific days always runs from the first of them to the
 * last, so everything that reads only the start and end dates - what is
 * still coming up, the order of the listings, the dates line, the
 * calendar - stays right without knowing the days exist. Only what asks
 * "is it on in this window?" has to look at them.
 */
final class ListingDays
{
    /**
     * The attributes to save and the days to keep, given the days chosen.
     *
     * No days leaves the listing on every day of its run. One day is simply
     * a one-day listing. Otherwise the run is pulled in to the first and
     * last day, and when every day between them is chosen there is nothing
     * the run does not already say, so no days are kept.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $days  as "2026-11-03"
     * @return array{attributes: array<string, mixed>, days: list<string>}
     */
    public function normalise(array $attributes, array $days): array
    {
        $days = array_values(array_unique($days));
        sort($days);

        if ($days === []) {
            return ['attributes' => $attributes, 'days' => []];
        }

        $first = $days[0];
        $last = $days[array_key_last($days)];

        if ($first === $last) {
            return ['attributes' => [...$attributes, 'start_date' => $first, 'end_date' => null], 'days' => []];
        }

        $daysInRun = (int) Carbon::parse($first)->diffInDays(Carbon::parse($last)) + 1;

        return [
            'attributes' => [...$attributes, 'start_date' => $first, 'end_date' => $last],
            'days' => count($days) === $daysInRun ? [] : $days,
        ];
    }

    /**
     * Save the listing and its days together, so a listing is never left on
     * every day of a run it was only meant to be on some days of.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $days  as "2026-11-03"
     */
    public function save(Listing $listing, array $attributes, array $days): void
    {
        ['attributes' => $attributes, 'days' => $days] = $this->normalise($attributes, $days);

        DB::transaction(function () use ($listing, $attributes, $days): void {
            $listing->fill($attributes)->save();

            $this->writeDays($listing, $days);
        });
    }

    /**
     * Replace a saved listing's days with these, already normalised. The
     * Office calls this once Nova has saved the listing, inside Nova's own
     * transaction, as the Dashboard does through save().
     *
     * @param  array<int, string>  $days  as "2026-11-03"
     */
    public function writeDays(Listing $listing, array $days): void
    {
        $listing->days()->delete();

        ListingDay::query()->insert(array_map(
            fn (string $day): array => ['listing_id' => $listing->getKey(), 'day' => $day],
            $days,
        ));
    }

    /**
     * Give a copy of a listing the original's days that fall within the
     * copy's own run. A copy left on the original's dates gets all of them;
     * one moved past every day gets none and keeps the dates it was given.
     */
    public function copy(Listing $original, Listing $copy): void
    {
        $firstDay = $copy->start_date->toDateString();
        $lastDay = ($copy->end_date ?? $copy->start_date)->toDateString();

        $days = $original->days
            ->map(fn (ListingDay $day): string => $day->day->toDateString())
            ->filter(fn (string $day): bool => $day >= $firstDay && $day <= $lastDay)
            ->all();

        $this->save($copy, [], $days);
    }
}
