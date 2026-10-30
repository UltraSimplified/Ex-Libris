<?php

namespace App\Nova;

/**
 * The accounts trusted with venues, that list what is on at them.
 */
class VenueManager extends RoleList
{
    public static function label(): string
    {
        return 'Venue managers';
    }

    public static function singularLabel(): string
    {
        return 'Venue manager';
    }

    protected static function role(): string
    {
        return 'is_venue_mgr';
    }
}
