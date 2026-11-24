<?php

namespace App\Nova\Cards;

use App\Models\Promoter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who has the most coming up, over the promoters index.
 */
class PromoterLeaders extends Leaderboard
{
    protected function heading(): string
    {
        return 'Top '.static::LEADERS.' Promoters';
    }

    protected function emptyText(): string
    {
        return 'No upcoming events are attributed to a promoter yet.';
    }

    protected function leaderQuery(): Builder
    {
        return Promoter::query();
    }

    protected function link(): string
    {
        return '/resources/promoters';
    }
}
