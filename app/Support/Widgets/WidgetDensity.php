<?php

namespace App\Support\Widgets;

/**
 * How much of each event a widget shows, and how big. A class on the
 * widget rather than a template of its own, so every template has all
 * four.
 */
enum WidgetDensity: string
{
    case Compact = 'compact';
    case Concise = 'concise';
    case Detailed = 'detailed';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Compact => 'Compact',
            self::Concise => 'Concise',
            self::Detailed => 'Detailed',
            self::Large => 'Large',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Compact => 'One line each: date, title and a button. For sidebars and footers.',
            self::Concise => 'A small picture beside the date, title and venue.',
            self::Detailed => 'Cards with the picture, category, date and time, price and a button.',
            self::Large => 'Big pictures with the title and date laid over them.',
        };
    }
}
