<?php

namespace App\Nova;

/**
 * The friendly websites the office has made publishers, that put the events
 * they have bookmarked on their own site with gig widgets.
 */
class Publisher extends RoleList
{
    public static function label(): string
    {
        return 'Publishers';
    }

    public static function singularLabel(): string
    {
        return 'Publisher';
    }

    protected static function role(): string
    {
        return 'is_publisher';
    }
}
