<?php

namespace App\Nova\Cards;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where the most is happening.
 */
class VenueLeaders extends Leaderboard
{
    protected function heading(): string
    {
        return 'Venues';
    }

    /**
     * "Top" moves down to the list, where it describes what it is: the
     * heading now names the tile, as the promoters one does.
     */
    protected function listHeading(): string
    {
        return 'Top '.static::LEADERS.' Venues';
    }

    protected function emptyText(): string
    {
        return 'No venue has an upcoming event.';
    }

    /**
     * @return array<string, int>
     */
    protected function counts(): array
    {
        return [
            'Venues' => Venue::count(),
            'Upcoming events' => $this->upcomingEvents(),
        ];
    }

    protected function leaderQuery(): Builder
    {
        return Venue::query();
    }

    protected function link(): string
    {
        return '/resources/venues';
    }

    protected function linkText(): string
    {
        return 'View all venues';
    }
}
