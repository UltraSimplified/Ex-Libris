<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\Venue;
use App\Services\Bookmarks\Bookmarks;
use App\Support\ListingDayHeadings;
use App\Support\ListingMeta;
use App\Support\SiteMeta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\OnceProp;
use Inertia\Response;

class ListingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function all()
    {
        $listings = Listing::withCardDetails()
            ->current()
            ->byNextDay(today())
            ->get();

        return Inertia::render('Listings', [
            'title' => 'Listings.',
            'listings' => $listings,
            'all' => true,
            'today' => false,
            'tomorrow' => false,
            'thisWeek' => false,
            'nextWeek' => false,
            'venue' => null,
            'genres' => $this->genres(),
            ...ListingDayHeadings::for(today()),
        ]);

    }

    /**
     * The events this visitor has saved, on their account or, if they have
     * not signed in, in their browser.
     *
     * Only what is still to come: a bookmark for something that has been
     * and gone is cleared down by the scheduler, and showing it in the
     * meantime would just be a list of things missed.
     */
    public function bookmarked(Request $request): Response
    {
        $listings = Bookmarks::for($request)
            ->listings()
            ->withCardDetails()
            ->current()
            ->byNextDay(today())
            ->get();

        return Inertia::render('Listings', [
            'title' => 'Bookmarked events.',
            'listings' => $listings,
            'all' => false,
            'today' => false,
            'tomorrow' => false,
            'thisWeek' => false,
            'nextWeek' => false,
            'bookmarked' => true,
            'venue' => null,
            'genres' => $this->genres(),
            ...ListingDayHeadings::for(today()),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function dates($start_date = null, $end_date = null)
    {
        $title = 'Listings for '.$this->formatDateRange($start_date, $end_date).'.';
        $listings = Listing::withCardDetails()
            ->runningBetween($start_date, $end_date)
            ->byNextDay($start_date)
            ->get();

        return Inertia::render('Listings', [
            'title' => $title,
            'listings' => $listings,
            'all' => true,
            'today' => false,
            'tomorrow' => false,
            'thisWeek' => false,
            'nextWeek' => false,
            'venue' => null,
            'genres' => $this->genres(),
            ...ListingDayHeadings::for(Carbon::parse($start_date), headed: ! Carbon::parse($start_date)->isSameDay($end_date)),
        ]);
    }

    /**
     * A venue's page: its cover photo, description, address and map, then
     * what is on there. A venue that does not exist is not found, rather
     * than a page about nobody.
     */
    public function venue($id = null, $slug = null)
    {
        $venue = Venue::findOrFail($id);

        $listings = Listing::withCardDetails()
            ->where('venue_id', $venue->id)
            ->current()
            ->byNextDay(today())
            ->get();

        return Inertia::render('Listings', [
            'title' => 'Listings for '.$venue['name'].'.',
            'listings' => $listings,
            'all' => true,
            'today' => false,
            'thisWeek' => false,
            'nextWeek' => false,
            'venue' => $venue->append('website_link'),
            'meta' => SiteMeta::venue($venue)->toArray(),
            ...ListingDayHeadings::for(today()),
        ]);
    }

    /**
     * What a promoter has on, soonest on first, under its banner,
     * about and contact details. The slug is only for the address's sake;
     * the id finds the promoter.
     */
    public function promoter(int $id, ?string $slug = null): Response
    {
        $promoter = Promoter::findOrFail($id);

        $listings = Listing::withCardDetails()
            ->where('promoter_id', $promoter->id)
            ->current()
            ->byNextDay(today())
            ->get();

        return Inertia::render('Listings', [
            'title' => 'Listings for '.$promoter->name.'.',
            'listings' => $listings,
            'all' => true,
            'today' => false,
            'thisWeek' => false,
            'nextWeek' => false,
            'venue' => null,
            'promoter' => $promoter->append('website_link'),
            // Its festivals with something still to come; finished ones keep
            // their own pages, but are not offered from here.
            'festivals' => $promoter->festivals()
                ->whereHas('listings', fn ($listings) => $listings->current())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map->only(['id', 'name']),
            'meta' => SiteMeta::promoter($promoter)->toArray(),
            ...ListingDayHeadings::for(today()),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function by_category($slug = null)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $listings = Listing::withCardDetails()
            ->where('category_id', $category['id'])
            ->current()
            ->byNextDay(today())
            ->get();

        return Inertia::render('Listings', [
            'title' => 'Listings for '.$category['name'].'.',
            'listings' => $listings,
            'all' => true,
            'today' => false,
            'thisWeek' => false,
            'nextWeek' => false,
            'venue' => null,
            ...ListingDayHeadings::for(today()),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function today()
    {
        $listings = Listing::withCardDetails()
            ->runningOn(today())
            ->byNextDay(today())
            ->get();

        return Inertia::render('Listings', [
            'title' => 'Today\'s listings.',
            'listings' => $listings,
            'all' => false,
            'today' => 1,
            'tomorrow' => false,
            'thisWeek' => false,
            'nextWeek' => false,
            'venue' => null,
            'genres' => $this->genres(),
            ...ListingDayHeadings::for(today(), headed: false),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function tomorrow()
    {
        $listings = Listing::withCardDetails()
            ->runningOn(today()->addDay())
            ->byNextDay(today()->addDay())
            ->get();

        $title = now()->addDay()->format('l')."'s listings";

        return Inertia::render('Listings', [
            'title' => $title,
            'listings' => $listings,
            'all' => false,
            'today' => false,
            'tomorrow' => 1,
            'thisWeek' => false,
            'nextWeek' => false,
            'venue' => null,
            'genres' => $this->genres(),
            ...ListingDayHeadings::for(today()->addDay(), headed: false),
        ]);
    }

    public function thisweek()
    {
        $listings = Listing::withCardDetails()
            ->runningBetween(today(), today()->endOfWeek())
            ->byNextDay(today())
            ->get();

        return Inertia::render('Listings', [
            'title' => 'This week\'s listings',
            'listings' => $listings,
            'all' => false,
            'today' => false,
            'tomorrow' => false,
            'thisWeek' => 1,
            'nextWeek' => false,
            'venue' => null,
            'genres' => $this->genres(),
            ...ListingDayHeadings::for(today()),
        ]);
    }

    public function nextweek()
    {
        $listings = Listing::withCardDetails()
            ->runningBetween(today()->addWeek()->startOfWeek(), today()->addWeek()->endOfWeek())
            ->byNextDay(today()->addWeek()->startOfWeek())
            ->get();

        return Inertia::render('Listings', [
            'title' => 'Next week\'s listings.',
            'listings' => $listings,
            'all' => false,
            'today' => false,
            'tomorrow' => false,
            'thisWeek' => false,
            'nextWeek' => 1,
            'venue' => null,
            'genres' => $this->genres(),
            ...ListingDayHeadings::for(today()->addWeek()->startOfWeek()),
        ]);
    }

    /**
     * Show a listing on its own, framed in the Office or the dashboard.
     *
     * The same page the public gets, so what is being read is what runs,
     * but without the site around it. Held to whoever the listing belongs
     * to and to the office.
     */
    public function preview(Listing $listing): Response
    {
        Gate::authorize('view', $listing);

        $listing->load(Listing::CARD_RELATIONS);

        return Inertia::render('Listing', [
            'title' => $listing->title,
            'listing' => $listing->append('body_links'),
            'meta' => ListingMeta::for($listing)->toArray(),
            'preview' => true,
        ]);
    }

    public function listing($id = null, $slug = null)
    {
        if (! $id && ! $slug) {
            return redirect()->route('listings');
        }

        $listing = Listing::withCardDetails()
            ->where('id', $id)
            ->firstOrFail();

        return Inertia::render('Listing', [
            'title' => $listing['title'],
            'listing' => $listing->append(['ticket_link', 'body_links']),
            'meta' => ListingMeta::for($listing)->toArray(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Listing $listing)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Listing $listing)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Listing $listing)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Listing $listing)
    {
        //
    }

    /**
     * The genres to narrow a list of listings down by: every category that
     * has something still to come, whatever dates are being looked at, so
     * the row stays put as the reader moves between Today and This Week.
     * Sent once and kept by the page from then on.
     */
    private function genres(): OnceProp
    {
        return Inertia::once(fn () => Category::query()
            ->whereHas('listings', fn ($listings) => $listings->current())
            ->orderBy('name')
            ->get(['id', 'name', 'slug']));
    }

    public function formatDateRange(string $start, string $end): string
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        // Same day: no range needed
        if ($start->isSameDay($end)) {
            return $start->format('j F Y') === $end->format('j F Y')
                ? $start->format('j F')
                : $start->format('j F Y');
        }

        // Different years: fully qualify both sides
        if (! $start->isSameYear($end)) {
            return $start->format('j F Y').' — '.$end->format('j F Y');
        }

        // Same year, different months: drop the years
        if (! $start->isSameMonth($end)) {
            return $start->format('j F').' — '.$end->format('j F');
        }

        // Same month and year: drop the first month too
        return $start->format('j').' — '.$end->format('j F');
    }
}
