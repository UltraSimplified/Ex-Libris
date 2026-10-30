<?php

namespace App\Nova;

/**
 * Everybody credited in the magazine, whether or not they ever sign in.
 */
class Writer extends RoleList
{
    public static function label(): string
    {
        return 'Writers';
    }

    public static function singularLabel(): string
    {
        return 'Writer';
    }

    protected static function role(): string
    {
        return 'is_writer';
    }

    protected static function activeWriterOnIndex(): bool
    {
        return true;
    }
}
