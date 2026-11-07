<?php

namespace App\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * The accounts that hold one role - the writers, the venue managers, the
 * promoters or the publishers - by name and all on the one page, with a
 * Create button that makes another with the role already ticked.
 */
abstract class RoleList extends UserList
{
    /**
     * @var array<string, string>
     */
    public static $indexDefaultOrder = ['name' => 'asc'];

    /**
     * The column that marks the role.
     */
    abstract protected static function role(): string;

    /**
     * Never more than one page. There are a few dozen of each, and a list
     * that stops at 25 hides the rest behind a click.
     *
     * @return array<int, int>
     */
    public static function perPageOptions(): array
    {
        return [1000];
    }

    protected static function narrow(Builder $query): Builder
    {
        return $query->where(static::role(), true);
    }

    protected static function presetRole(): ?string
    {
        return static::role();
    }
}
