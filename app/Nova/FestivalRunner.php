<?php

namespace App\Nova;

/**
 * The accounts the office has let manage their promoters' festivals from
 * Backstage. Not called Festival, which is the festivals themselves, but
 * listed under Users as Festivals, as promoter accounts are as Promoters.
 */
class FestivalRunner extends RoleList
{
    public static function label(): string
    {
        return 'Festivals';
    }

    public static function singularLabel(): string
    {
        return 'Festival account';
    }

    public static function uriKey(): string
    {
        return 'runs-festivals';
    }

    protected static function role(): string
    {
        return 'runs_festivals';
    }
}
