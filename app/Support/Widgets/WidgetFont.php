<?php

namespace App\Support\Widgets;

/**
 * The typefaces a widget can be set in. A frame cannot borrow the host
 * page's font, so these are the system's own families, which load nothing
 * and sit comfortably on most sites.
 */
enum WidgetFont: string
{
    case Sans = 'sans';
    case Serif = 'serif';
    case Rounded = 'rounded';
    case Mono = 'mono';

    public function label(): string
    {
        return match ($this) {
            self::Sans => 'Sans serif',
            self::Serif => 'Serif',
            self::Rounded => 'Rounded',
            self::Mono => 'Typewriter',
        };
    }

    public function stack(): string
    {
        return match ($this) {
            self::Sans => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
            self::Serif => 'Charter, "Bitstream Charter", "Sitka Text", Cambria, Georgia, serif',
            self::Rounded => 'ui-rounded, "Hiragino Maru Gothic ProN", Quicksand, Comfortaa, Manjari, "Arial Rounded MT", sans-serif',
            self::Mono => 'ui-monospace, "Cascadia Code", "Source Code Pro", Menlo, Consolas, monospace',
        };
    }
}
