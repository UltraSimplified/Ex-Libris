<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Support\ListingCalendar;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * A listing as a calendar file, for the Add to calendar link.
 *
 * Inline rather than as an attachment, so a phone that understands the file
 * offers to add the event straight away. Everything in it is already on the
 * listing's public page.
 */
class CalendarController extends Controller
{
    public function __invoke(Listing $listing): Response
    {
        $filename = (Str::slug($listing->slug) ?: 'event').'.ics';

        return response(ListingCalendar::for($listing), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'public, max-age=300',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
