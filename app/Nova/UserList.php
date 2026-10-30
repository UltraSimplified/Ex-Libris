<?php

namespace App\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * One kind of account on a list of its own, under Users in the Office's
 * menu: the same accounts and the same form as Users, only narrowed.
 */
abstract class UserList extends User
{
    /**
     * An account turns up once in the search, under Users, rather than once
     * more for every list it is on.
     *
     * @var bool
     */
    public static $globallySearchable = false;

    /**
     * Narrow the accounts to this list's.
     */
    abstract protected static function narrow(Builder $query): Builder;

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return static::narrow(parent::indexQuery($request, $query));
    }

    /**
     * Everybody on the list is the same kind of account, so its table is
     * the avatar, the name and the email alone.
     */
    protected static function rolesOnIndex(): bool
    {
        return false;
    }

    /**
     * The role filter is for Users, where everybody is; a list is already
     * one kind of account.
     *
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }
}
