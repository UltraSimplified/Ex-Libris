<?php

namespace App\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Narrows the users to the writers, the venue managers or the promoters.
 * Ticking more than one shows anybody who is any of them; ticking none
 * shows everybody.
 *
 * Read from the columns, as the Office grants them, so staff - who hold
 * every role without being marked with any - only appear where they have
 * been ticked.
 */
class UserRole extends BooleanFilter
{
    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Role';

    /**
     * Apply the filter to the given query.
     *
     * @param  array<string, bool>  $value
     */
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        $roles = array_keys(array_filter((array) $value));

        if ($roles === []) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($roles): void {
            foreach ($roles as $role) {
                $query->orWhere($role, true);
            }
        });
    }

    /**
     * Get the filter's available options.
     *
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            'Writers' => 'is_writer',
            'Venue managers' => 'is_venue_mgr',
            'Promoters' => 'is_promoter',
        ];
    }
}
