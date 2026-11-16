<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\FestivalRequest;
use App\Models\Festival;
use App\Services\Images\BrandImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets an account that runs festivals make and keep its promoters'
 * festivals.
 *
 * Unlike promoters, festivals are made here: the office trusts the account
 * with Runs Festivals, and what it saves goes straight up, as its listings
 * do. A festival stays with the promoter it was made for.
 */
class FestivalController extends Controller
{
    public function __construct(
        private BrandImage $images,
    ) {}

    /**
     * Show the festivals this account runs, or one promoter's of them.
     *
     * Only a promoter the account runs narrows the list: any other id
     * names nothing here and is ignored, as on the listings.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Festival::class);

        $promoter = $request->user()->promoters()->find($request->integer('promoter'), ['id', 'name']);

        $festivals = $request->user()
            ->managedFestivals()
            ->when($promoter, fn (Builder $query): Builder => $query->where('festivals.promoter_id', $promoter->id))
            ->with('promoter:id,name')
            ->withCount(['listings' => fn ($query) => $query->current()])
            ->orderBy('name')
            ->get();

        return Inertia::render('dashboard/festivals/Index', [
            'festivals' => $festivals,
            'promoter' => $promoter?->only(['id', 'name']),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Show the form for a new festival, for one of this account's
     * promoters, already chosen when it arrived from that promoter.
     *
     * It can start from a festival the account already runs, as next
     * year's edition of an annual one does: the form arrives filled in
     * with that festival's details and pictures, for that festival's
     * promoter. Any other id names nothing here and is ignored.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Festival::class);

        $promoters = $request->user()->promoters()->orderBy('name')->get(['id', 'name']);
        $earlier = $request->user()->managedFestivals()->latest()->get();
        $startingFrom = $earlier->firstWhere('id', $request->integer('from'));
        $chosen = $promoters->firstWhere('id', $startingFrom->promoter_id ?? $request->integer('promoter')) ?? $promoters->first();

        return Inertia::render('dashboard/festivals/Create', [
            'promoters' => $promoters,
            'promoterId' => $chosen?->id,
            'earlier' => $earlier->map->only(['id', 'name'])->values(),
            'startingFrom' => $startingFrom?->only([
                'id', 'name', 'website', 'email', 'phone', 'about', 'logo', 'banner',
                'brand_colour', 'accent_colour1', 'accent_colour2', 'brand_background_colour',
            ]),
        ]);
    }

    /**
     * Make the festival, with whatever pictures came with it, and copies of
     * those it kept from the festival it was started from.
     */
    public function store(FestivalRequest $request): RedirectResponse
    {
        Gate::authorize('create', Festival::class);

        $festival = new Festival($request->profile());
        $festival->promoter_id = $request->integer('promoter_id');

        $earlier = $request->earlierFestival();

        if ($earlier) {
            $this->images->copyPicturesFrom($earlier, $festival, $request);
        }

        $this->images->saveWithPictures($festival, $request);

        return to_route('dashboard.festivals.index')
            ->with('status', 'Festival created.');
    }

    /**
     * Show the form for editing the given festival.
     */
    public function edit(Festival $festival): Response
    {
        Gate::authorize('update', $festival);

        return Inertia::render('dashboard/festivals/Edit', [
            'festival' => $festival->load('promoter:id,name'),
        ]);
    }

    /**
     * Update the given festival. Its promoter is not asked for, and
     * anything sent for it is left out.
     */
    public function update(FestivalRequest $request, Festival $festival): RedirectResponse
    {
        Gate::authorize('update', $festival);

        $festival->fill($request->profile());

        $this->images->saveWithPictures($festival, $request);

        return to_route('dashboard.festivals.index')
            ->with('status', 'Festival updated.');
    }

    /**
     * Delete the festival. Its listings stay, in no festival, and its
     * widgets and pictures go with it.
     */
    public function destroy(Festival $festival): RedirectResponse
    {
        Gate::authorize('delete', $festival);

        $festival->delete();

        return to_route('dashboard.festivals.index')
            ->with('status', 'Festival deleted.');
    }
}
