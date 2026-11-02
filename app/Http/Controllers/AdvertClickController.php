<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesVisitorLocation;
use App\Models\Advert;
use App\Models\AdvertEvent;
use App\Support\Crawler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdvertClickController extends Controller
{
    use ResolvesVisitorLocation;

    /**
     * Log a click event against the advert, unless a crawler sent the request,
     * then redirect to the advertiser's URL with UTM parameters appended. A
     * sponsorship's clicks are tagged as such, by the advert rather than the
     * link, so the sponsor can tell them apart in their own figures.
     *
     * @return RedirectResponse
     */
    public function __invoke(Request $request, Advert $advert)
    {
        if (! $advert->link) {
            abort(404);
        }

        if (! Crawler::sent($request)) {
            $advert->events()->create([
                'type' => 'click',
                'page' => $request->query('from'),
                'variant' => $request->query('v'),
                'placement' => AdvertEvent::placementFrom($request->query('p')),
                ...$this->locationAttributes($request),
            ]);
        }

        $utm = http_build_query([
            'utm_source' => 'goodonpaper',
            'utm_medium' => $advert->sponsorship ? 'sponsorship' : 'banner',
            'utm_campaign' => $advert->campaign ?? 'advert-'.$advert->id,
        ]);

        $separator = str_contains($advert->link, '?') ? '&' : '?';

        return redirect()->away($advert->link.$separator.$utm);
    }
}
