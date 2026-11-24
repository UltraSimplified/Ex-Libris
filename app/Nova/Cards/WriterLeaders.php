<?php

namespace App\Nova\Cards;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who has written the most, over the features index beside what was read.
 *
 * Replaces a chart of writers plotted against articles written, which
 * answered a question nobody was asking: the useful thing is who is
 * carrying the body of work.
 */
class WriterLeaders extends Leaderboard
{
    /**
     * A third, to sit in a row with the two reading cards.
     */
    public $width = '1/3';

    protected function heading(): string
    {
        return 'Top '.static::LEADERS.' Contributors';
    }

    protected function emptyText(): string
    {
        return 'No writer has a published article yet.';
    }

    /**
     * The accounts marked as writing, rather than the writer records they
     * were made from. The column rather than hasRole(): staff hold every
     * role without being marked with any, and a leaderboard of writers
     * should not be padded out with the office.
     */
    protected function leaderQuery(): Builder
    {
        return User::writers();
    }

    /**
     * Everything they have ever had published. Unlike an event, an article
     * does not stop counting once its date has passed.
     */
    protected function relation(): string
    {
        return 'features';
    }

    protected function constrain(Builder $query): Builder
    {
        return $query;
    }

    protected function unit(): string
    {
        return 'article';
    }

    protected function link(): string
    {
        return '/resources/users';
    }
}
