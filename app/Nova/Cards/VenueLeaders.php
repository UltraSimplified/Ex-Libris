<?php

namespace App\Nova\Cards;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where the most is happening, over the venues index.
 */
class VenueLeaders extends Leaderboard
{
    protected function heading(): string
    {
        return 'Top '.static::LEADERS.' Venues';
    }

    protected function emptyText(): string
    {
        return 'No venue has an upcoming event.';
    }

    protected function leaderQuery(): Builder
    {
        return Venue::query();
    }

    protected function link(): string
    {
        return '/resources/venues';
    }
}
