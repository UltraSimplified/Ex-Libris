<?php

namespace App\Nova\Cards;

use App\Models\Feature;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who is writing, and who has written the most.
 *
 * Replaces a chart of writers plotted against articles written, which
 * answered a question nobody was asking: the useful thing is the roster,
 * the body of work, and who is carrying it.
 */
class WriterLeaders extends Leaderboard
{
    protected function heading(): string
    {
        return 'Writers';
    }

    protected function emptyText(): string
    {
        return 'No writer has a published article yet.';
    }

    protected function listHeading(): string
    {
        return 'Top '.static::LEADERS.' Contributors';
    }

    /**
     * @return array<string, int>
     */
    protected function counts(): array
    {
        return [
            'Writers' => User::writers()->count(),
            'Articles' => Feature::count(),
        ];
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

    protected function nameColumn(): string
    {
        return 'name';
    }

    protected function link(): string
    {
        return '/resources/users';
    }

    protected function linkText(): string
    {
        return 'View all accounts';
    }
}
