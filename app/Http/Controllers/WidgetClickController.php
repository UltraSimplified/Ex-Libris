<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Widget;
use App\Models\WidgetDailyStat;
use App\Support\AffiliateLink;
use Illuminate\Http\RedirectResponse;

/**
 * A ticket button pressed in a gig widget: counted, then sent on to the
 * venue's own ticket page, tagged as Good on Paper's referral so the venue
 * can see in its own analytics what the widget sold.
 *
 * Going through here rather than straight to the venue means the button
 * can later lead to Good on Paper's own checkout, for events whose tickets
 * we sell, without anybody changing the snippet on their site.
 */
class WidgetClickController extends Controller
{
    public function __invoke(Widget $widget, Listing $listing): RedirectResponse
    {
        abort_unless((int) $listing->{$widget->listingsColumn()} === (int) $widget->owner_id, 404);

        $link = AffiliateLink::tag(
            trim((string) ($listing->event_link ?: $listing->venue?->url)),
            'widget-'.$widget->id,
            'widget',
        );

        abort_if($link === null, 404);

        WidgetDailyStat::record($widget, 'clicks');

        return redirect()->away($link);
    }
}
