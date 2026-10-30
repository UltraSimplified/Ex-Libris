<?php

namespace App\Services\Widgets;

use App\Models\Listing;
use App\Models\Venue;
use App\Models\Widget;
use App\Support\AffiliateLink;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The events a widget shows, as the few things its template prints.
 *
 * Never the listing model itself: what reaches somebody else's website is
 * chosen field by field here, so nothing added to a listing later can
 * turn up in an embed by accident.
 */
class WidgetFeed
{
    /**
     * The owner's events that have not finished, soonest first,
     * as many as the widget is set to show.
     *
     * Soonest to start rather than the listings pages' soonest to finish:
     * a widget is "what's on next", and a month-long exhibition ending today
     * should not sit above tonight's gig. One already under way shows as
     * running until its last day.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function events(Widget $widget): Collection
    {
        $settings = $widget->settings();

        return Listing::query()
            ->select([
                'id', 'title', 'slug', 'thumbnail', 'start_date', 'end_date',
                'event_time_description', 'additional_date_information', 'ticket_price',
                'sold_out', 'event_link', 'venue_id', 'category_id',
            ])
            ->with(['venue:id,name,town,url', 'category:id,name,slug'])
            ->current()
            ->where($widget->listingsColumn(), $widget->owner_id)
            ->orderBy('start_date')
            ->orderBy('title')
            ->limit($settings->count + 1)
            ->get()
            ->map(fn (Listing $listing): array => $this->event($widget, $listing));
    }

    /**
     * Where the "Listings by Good on Paper" credit and the "more events"
     * link go: the venue's page of listings, or the listings for a promoter,
     * who has no page of their own yet.
     */
    public function ownerPage(Widget $widget): string
    {
        $owner = $widget->owner;

        $path = $owner instanceof Venue
            ? route('Venue', ['id' => $owner->id, 'slug' => Str::slug($owner->name)], false)
            : route('Listings', [], false);

        return $this->onSite($path, $widget, 'widget-credit');
    }

    /**
     * @return array<string, mixed>
     */
    private function event(Widget $widget, Listing $listing): array
    {
        /** @var CarbonInterface $start */
        $start = $listing->start_date;
        $end = $listing->end_date && $listing->end_date->gt($start) ? $listing->end_date : null;
        $venue = $listing->venue;

        $page = $this->onSite(
            route('Listing', ['id' => $listing->id, 'slug' => $listing->slug ?: Str::slug($listing->title)], false),
            $widget,
            'widget',
        );

        $ticketLink = trim((string) ($listing->event_link ?: $venue?->url));

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'image' => $this->imageUrl($listing->thumbnail),
            'category' => $listing->category?->name,
            'venue' => $venue && $venue->id ? trim($venue->name.($venue->town ? ', '.$venue->town : '')) : null,
            'start' => $start,
            'end' => $end,
            'under_way' => $end !== null && $start->lt(today()),
            'when' => $this->when($start, $end),
            'time' => trim((string) $listing->event_time_description) ?: null,
            'dates_note' => trim((string) $listing->additional_date_information) ?: null,
            'price' => trim((string) $listing->ticket_price) ?: null,
            'sold_out' => (bool) $listing->sold_out,
            'page' => $page,
            'tickets' => AffiliateLink::tag($ticketLink, 'widget-'.$widget->id, 'widget') !== null
                ? route('widgets.click', ['widget' => $widget->key, 'listing' => $listing->id])
                : null,
        ];
    }

    /**
     * The day it is on, or the run it spans: "Tue 20 Oct", "Tue 20 Oct –
     * Sat 30 Nov", or "Until Sat 30 Nov" once it has begun.
     */
    private function when(CarbonInterface $start, ?CarbonInterface $end): string
    {
        if ($end === null) {
            return $start->format('D j M');
        }

        if ($start->lt(today())) {
            return 'Until '.$end->format('D j M');
        }

        return $start->format('D j M').' – '.$end->format('D j M');
    }

    /**
     * A thumbnail is stored as a filename on the CDN, or occasionally as a
     * full address put there by hand.
     */
    private function imageUrl(?string $thumbnail): ?string
    {
        $thumbnail = trim((string) $thumbnail);

        if ($thumbnail === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $thumbnail) === 1) {
            return $thumbnail;
        }

        return rtrim((string) config('filesystems.disks.do.url'), '/').'/'.ltrim($thumbnail, '/');
    }

    /**
     * A page on Good on Paper itself, tagged as sent by the widget.
     *
     * Built on the site's own address rather than the request's, because
     * the widget is served from a host of its own.
     */
    private function onSite(string $path, Widget $widget, string $medium): string
    {
        $url = rtrim((string) config('app.url'), '/').$path;

        return AffiliateLink::tag($url, 'widget-'.$widget->id, $medium) ?? $url;
    }
}
