<?php

namespace App\Nova;

use App\Models\User as UserModel;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Everybody who has signed up on the site and is nothing more: not a
 * writer, a venue manager or a promoter, and not one of the office. Newest
 * first, a page at a time.
 */
class Guest extends UserList
{
    protected static function narrow(Builder $query): Builder
    {
        foreach (UserModel::ROLES as $role) {
            $query->where($role, false);
        }

        return $query->whereNotIn('email', UserModel::OFFICE_EMAILS);
    }

    /**
     * A guest signs up on the site; the office never makes one, so there is
     * no Create button.
     */
    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }
}
