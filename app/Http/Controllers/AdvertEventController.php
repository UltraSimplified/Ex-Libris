<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesVisitorLocation;
use App\Models\Advert;
use App\Models\AdvertEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdvertEventController extends Controller
{
    use ResolvesVisitorLocation;

    /**
     * Log a viewable impression for the given advert, fired by the
     * client-side IntersectionObserver beacon. Returns 204 as the
     * beacon never reads the response.
     *
     * @return Response
     */
    public function impression(Request $request, Advert $advert)
    {
        $advert->events()->create([
            'type' => 'impression',
            'page' => $request->input('page'),
            'variant' => $request->input('variant'),
            'placement' => AdvertEvent::placementFrom($request->input('placement')),
            ...$this->locationAttributes($request),
        ]);

        return response()->noContent();
    }
}
