<?php

namespace App\Nova\Cards;

use App\Models\Listing;
use App\Models\Promoter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who is on the books, and who has the most coming up.
 */
class PromoterLeaders extends Leaderboard
{
    protected function heading(): string
    {
        return 'Promoters';
    }

    protected function emptyText(): string
    {
        return 'No upcoming events are attributed to a promoter yet.';
    }

    protected function listHeading(): string
    {
        return 'Top '.static::LEADERS.' Promoters';
    }

    /**
     * @return array<string, int>
     */
    protected function counts(): array
    {
        return [
            'Promoters' => Promoter::count(),
            // Everything ever listed, promoted or not, finished or not.
            'Events listed' => Listing::count(),
            // Every save rather than every listing saved: one listing that
            // fifty people kept is fifty readings of it, and engagement is
            // what this is here to show.
            'Bookmarks' => DB::table('bookmarks')->count(),
        ];
    }

    protected function leaderQuery(): Builder
    {
        return Promoter::query();
    }

    protected function link(): string
    {
        return '/resources/promoters';
    }

    protected function linkText(): string
    {
        return 'View all promoters';
    }
}
