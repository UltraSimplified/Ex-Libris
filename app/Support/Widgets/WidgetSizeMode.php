<?php

namespace App\Support\Widgets;

/**
 * How a widget's frame is sized on the host page.
 */
enum WidgetSizeMode: string
{
    /**
     * As tall as its events, resized by the loader as they change. Fills
     * the width it is given.
     */
    case Auto = 'auto';

    /**
     * The width and height the owner declared, scrolling inside if the
     * events do not fit.
     */
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Fit the events',
            self::Fixed => 'Fixed size',
        };
    }
}
