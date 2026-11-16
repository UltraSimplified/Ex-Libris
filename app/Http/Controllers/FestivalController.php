<?php

namespace App\Http\Controllers;

use App\Models\Festival;
use App\Support\ListingDayHeadings;
use App\Support\SiteMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A festival's page: its banner, about and contact details over its
 * programme, headed by day as a promoter's page is.
 *
 * While anything in it is still to come, the programme is what is left,
 * counted from today. Once all of it has been, the page is a record of the
 * festival rather than a blank: the whole programme from its first day,
 * under a line saying it has finished.
 */
class FestivalController extends Controller
{
    /**
     * The slug is only for the address's sake; the id finds the festival,
     * so a festival renamed since a link was shared is still found.
     */
    public function show(int $id, ?string $slug = null): Response
    {
        $festival = Festival::with('promoter:id,name')->findOrFail($id);

        $finished = $festival->hasFinished();
        $firstDay = $finished ? Carbon::parse($festival->listings()->min('start_date')) : today();

        $listings = $festival->listings()
            ->withCardDetails()
            ->when(! $finished, fn (Builder $query): Builder => $query->current())
            ->byNextDay($firstDay)
            ->get();

        return Inertia::render('Listings', [
            'title' => 'Listings for '.$festival->name.'.',
            'listings' => $listings,
            'all' => true,
            'today' => false,
            'thisWeek' => false,
            'nextWeek' => false,
            'venue' => null,
            'festival' => $festival->append('website_link'),
            'finished' => $finished,
            'meta' => SiteMeta::festival($festival, $listings)->toArray(),
            ...ListingDayHeadings::for($firstDay),
        ]);
    }
}
