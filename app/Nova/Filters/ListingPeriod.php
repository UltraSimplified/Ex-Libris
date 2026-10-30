<?php

namespace App\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Restricts the listings index to what is still on, which is what the
 * index is almost always wanted for. Past listings stay reachable by
 * switching this filter rather than being hidden away.
 */
class ListingPeriod extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Show';

    /**
     * Apply the filter to the given query.
     *
     * A listing counts as finished only once its last day has passed, so a
     * run of dates is measured on its end date and a single-day event on
     * its start date.
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        $lastDay = 'COALESCE(end_date, start_date)';

        return match ($value) {
            'past' => $query->whereRaw("{$lastDay} < ?", [today()->toDateString()]),
            'all' => $query,
            default => $query->whereRaw("{$lastDay} >= ?", [today()->toDateString()]),
        };
    }

    /**
     * Get the filter's available options.
     *
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            'Current & upcoming' => 'upcoming',
            'Past' => 'past',
            'All listings' => 'all',
        ];
    }

    /**
     * The default value of the filter.
     */
    public function default(): string
    {
        return 'upcoming';
    }
}
