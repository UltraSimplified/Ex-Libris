<?php

namespace App\Support\Widgets;

use App\Models\Widget;

/**
 * What an owner copies into their own site to show a widget, in the three
 * forms different site builders allow.
 */
final class WidgetSnippets
{
    /**
     * Whether the permanent widget host is set up. Until it is, snippets
     * point at whatever host the dashboard was reached by, which is fine to
     * try out but not to paste anywhere for good.
     */
    public static function hostReady(): bool
    {
        return filled(config('widgets.domain'));
    }

    /**
     * @return array{script: string, iframe: string, link: string}
     */
    public static function for(Widget $widget): array
    {
        $embed = route('widgets.embed', ['widget' => $widget->key]);
        $loader = self::base().'/widget.js';
        $title = $widget->owner?->name ? 'Upcoming events at '.$widget->owner->name : 'Upcoming events';
        $height = $widget->height ?: 600;
        $width = $widget->size_mode === WidgetSizeMode::Fixed && $widget->width ? 'max-width:'.$widget->width.'px;' : '';

        return [
            'script' => sprintf(
                '<div data-gop-widget="%s" data-title="%s"></div>'."\n".'<script async src="%s"></script>',
                e($widget->key),
                e($title),
                e($loader),
            ),
            'iframe' => sprintf(
                '<iframe src="%s" title="%s" width="100%%" height="%d" style="border:0;%s" loading="lazy"></iframe>',
                e($embed),
                e($title),
                $height,
                $width,
            ),
            'link' => $embed,
        ];
    }

    private static function base(): string
    {
        return self::hostReady()
            ? request()->getScheme().'://'.config('widgets.domain')
            : rtrim(url('/'), '/');
    }
}
