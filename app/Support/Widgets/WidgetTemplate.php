<?php

namespace App\Support\Widgets;

/**
 * The layouts a gig widget can take. Each is a Blade view under
 * resources/views/embed, painted by the same brand colours.
 */
enum WidgetTemplate: string
{
    case List = 'list';

    public function label(): string
    {
        return match ($this) {
            self::List => 'List',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::List => 'Upcoming events, soonest first, as rows or cards.',
        };
    }

    public function view(): string
    {
        return 'embed.'.$this->value;
    }
}
