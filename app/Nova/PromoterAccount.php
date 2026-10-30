<?php

namespace App\Nova;

/**
 * The people who promote, as accounts. Not called Promoter, which is the
 * promoters themselves - the names their listings go under, kept under
 * Events - though it is labelled the same.
 */
class PromoterAccount extends RoleList
{
    public static function label(): string
    {
        return 'Promoters';
    }

    public static function singularLabel(): string
    {
        return 'Promoter';
    }

    protected static function role(): string
    {
        return 'is_promoter';
    }
}
