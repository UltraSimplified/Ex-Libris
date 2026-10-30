<?php

namespace App\Http\Controllers;

use App\Models\Widget;
use App\Services\Widgets\WidgetCache;
use App\Services\Widgets\WidgetFeed;
use App\Support\Widgets\WidgetDensity;
use App\Support\Widgets\WidgetFont;
use App\Support\Widgets\WidgetOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A gig widget as it appears inside the frame on somebody else's site.
 *
 * Served outside the web middleware group, so a view starts no session and
 * sets no cookie: every page view on every venue's site would otherwise
 * write a session row. The response may be framed by any site and kept by
 * browsers and the edge for a minute or two.
 */
class EmbedController extends Controller
{
    public function __construct(
        private WidgetFeed $feed,
        private WidgetCache $cache,
    ) {}

    public function show(Request $request, Widget $widget): Response
    {
        $preview = $request->boolean('preview');

        if ($preview) {
            $this->applyPreview($request, $widget);
        }

        $html = ! $widget->active && ! $preview
            ? view('embed.inactive')->render()
            : ($preview
                ? $this->render($widget, $preview)
                : $this->cache->remember($widget, 'page', fn (): string => $this->render($widget, false)));

        return response($html)
            ->header('Cache-Control', $preview ? 'no-store' : 'public, max-age=60, s-maxage=120, stale-while-revalidate=600');
    }

    private function render(Widget $widget, bool $preview): string
    {
        $events = $this->feed->events($widget);
        $settings = $widget->settings();

        return view($widget->template->view(), [
            'widget' => $widget,
            'settings' => $settings,
            'events' => $events->take($settings->count),
            'more' => $events->count() > $settings->count,
            'ownerName' => $widget->owner?->name,
            'ownerPage' => $this->feed->ownerPage($widget),
            'brand' => $widget->brand(),
            'preview' => $preview,
        ])->render();
    }

    /**
     * The dashboard previews unsaved changes by passing them in the query.
     * Only how the widget looks can be changed this way, nothing is saved,
     * and anything that is not a recognised value is ignored.
     */
    private function applyPreview(Request $request, Widget $widget): void
    {
        if ($density = WidgetDensity::tryFrom((string) $request->query('density'))) {
            $widget->density = $density;
        }

        if ($font = WidgetFont::tryFrom((string) $request->query('font'))) {
            $widget->font = $font;
        }

        if (is_array($options = $request->query('options'))) {
            $widget->options = WidgetOptions::from([...($widget->options ?? []), ...$options])->toArray();
        }

        if ($request->has('colours')) {
            $colours = $request->query('colours');
            $widget->colours = is_array($colours) ? array_map(fn ($value) => is_string($value) ? $value : null, $colours) : null;
        }
    }
}
