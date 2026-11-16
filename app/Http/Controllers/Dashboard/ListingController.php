<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ListingRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingDay;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Services\Images\ListingGallery;
use App\Services\Images\ListingThumbnail;
use App\Services\Listings\ListingDays;
use App\Services\Listings\ListingLineUp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    public function __construct(
        private ListingThumbnail $thumbnails,
        private ListingGallery $gallery,
        private ListingLineUp $lineUps,
        private ListingDays $days,
    ) {}

    /**
     * Show the listings belonging to the authenticated user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $editable = $user->editableListings();

        /*
         * The sidebar gives every venue and promoter this account runs a
         * section of its own, so the list can arrive narrowed to one of
         * them. Only what the account actually runs is recognised: any
         * other id names nothing here and is ignored rather than filtered
         * on, so a guessed number cannot be used to ask what is on
         * somewhere else.
         */
        $venue = $user->venues()->find($request->integer('venue'), ['id', 'name']);
        $promoter = $user->promoters()->find($request->integer('promoter'), ['id', 'name']);

        $editable
            ->when($venue, fn (Builder $query): Builder => $query->where('listings.venue_id', $venue->id))
            ->when($promoter, fn (Builder $query): Builder => $query->where('listings.promoter_id', $promoter->id));

        /*
         * What is still to come, unless somebody asks for the lot. An
         * account that has been listing for a while has far more behind it
         * than ahead of it, and the list is there to work from rather than
         * to look back over.
         */
        $showingAll = $request->boolean('all');

        $listings = (clone $editable)
            ->with(['venue', 'category', 'promoter'])
            // How many people have saved it, which is the only measure of
            // interest anybody here has before the doors open.
            ->withCount('bookmarkedBy')
            ->when(! $showingAll, fn (Builder $query): Builder => $query->current())
            ->orderByDesc('start_date')
            ->get();

        return Inertia::render('dashboard/listings/Index', [
            'listings' => $listings,
            'showingAll' => $showingAll,
            // What the list has been narrowed to, so the page can say so
            // and keep hold of it across the links that change the scope.
            'venue' => $venue?->only(['id', 'name']),
            'promoter' => $promoter?->only(['id', 'name']),
            // How many are behind the "show everything" link, so it can say
            // whether there is anything behind it at all.
            'pastCount' => (clone $editable)->count() - (clone $editable)->current()->count(),
            // Only an agreed account - one the office has linked to a
            // promoter or venue - adds and edits listings; anybody else is
            // shown what they sent in before, to read or delete.
            'canPublish' => $user->can('create', Listing::class),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Copy a listing, and open the copy for editing.
     *
     * Most of what somebody lists is a repeat of something they have
     * listed before - the same room, the same night, a month later - and
     * starting from the last one is quicker than starting from nothing.
     *
     * The copy is a copy: it gets its own image files rather than sharing
     * the original's, since deleting a listing deletes the files behind it.
     */
    public function duplicate(Request $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('replicate', $listing);

        $copy = $listing->replicate(['slug']);

        $copy->title = 'Copy of '.$listing->title;
        $copy->slug = null;
        $copy->user_id = $request->user()->id;
        $copy->thumbnail = $this->thumbnails->copy($listing->thumbnail);
        $copy->gallery = $this->gallery->encode(
            $this->gallery->copy($this->gallery->parse($listing->gallery))
        );

        $copy->save();
        $this->days->copy($listing, $copy);

        return to_route('dashboard.listings.edit', $copy)
            ->with('status', 'Listing duplicated. This is the copy.');
    }

    /**
     * Show the form for creating a new listing.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Listing::class);

        return Inertia::render('dashboard/listings/Create', [
            'categories' => $this->categories(),
            'venues' => $this->venues($request->user()),
            'promoters' => $this->promoters($request->user()),
            'managesVenue' => $request->user()->venues()->exists(),
            'canAddVenue' => $request->user()->can('create', Venue::class),
        ]);
    }

    /**
     * Store a newly created listing.
     */
    public function store(ListingRequest $request): RedirectResponse
    {
        Gate::authorize('create', Listing::class);

        $attributes = $this->attributesFrom($request);

        if ($request->hasFile('thumbnail')) {
            $attributes['thumbnail'] = $this->thumbnails->store($request->file('thumbnail'));
        }

        $attributes['gallery'] = $this->gallery->encode($this->addedGalleryImages($request));

        $listing = $request->user()->listings()->make();
        $this->days->save($listing, $attributes, $request->specificDays());

        return to_route('dashboard.listings.index')->with('status', 'Listing created.');
    }

    /**
     * Show the form for editing the given listing.
     */
    public function edit(Request $request, Listing $listing): Response
    {
        Gate::authorize('update', $listing);

        return Inertia::render('dashboard/listings/Edit', [
            'listing' => $listing,
            'specificDays' => $listing->days->map(fn (ListingDay $day): string => $day->day->toDateString()),
            'gallery' => $this->gallery->parse($listing->gallery),
            'lineUp' => $this->lineUps->artists($listing->line_up),
            'categories' => $this->categories(),
            'venues' => $this->venues($request->user(), $listing),
            'promoters' => $this->promoters($request->user(), $listing),
            'managesVenue' => $request->user()->venues()->exists(),
            'canAddVenue' => $request->user()->can('create', Venue::class),
        ]);
    }

    /**
     * Update the given listing.
     */
    public function update(ListingRequest $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('update', $listing);

        $attributes = $this->attributesFrom($request);
        $previousThumbnail = $listing->thumbnail;

        if ($request->hasFile('thumbnail')) {
            $attributes['thumbnail'] = $this->thumbnails->store($request->file('thumbnail'));
        } elseif ($request->boolean('remove_thumbnail')) {
            $attributes['thumbnail'] = null;
        }

        [$keptImages, $removedImages] = $this->reconcileGallery($request, $listing);
        $attributes['gallery'] = $this->gallery->encode(
            array_merge($keptImages, $this->addedGalleryImages($request))
        );

        $this->days->save($listing, $attributes, $request->specificDays());

        // Files are only unlinked once the row has saved, so a failure here
        // leaves the listing pointing at images that still exist.
        if (array_key_exists('thumbnail', $attributes)) {
            $this->thumbnails->delete($previousThumbnail);
        }

        $this->gallery->delete($removedImages);

        return to_route('dashboard.listings.index')->with('status', 'Listing updated.');
    }

    /**
     * Remove the given listing.
     */
    public function destroy(Listing $listing): RedirectResponse
    {
        Gate::authorize('delete', $listing);

        $gallery = $this->gallery->parse($listing->gallery);

        $listing->delete();

        $this->thumbnails->delete($listing->thumbnail);
        $this->gallery->delete($gallery);

        return to_route('dashboard.listings.index')
            ->with('status', 'Listing deleted.');
    }

    /**
     * The plain listing columns, with the line-up as submitted. The listing
     * cleans its line-up as it saves, as it does whoever writes it.
     *
     * @return array<string, mixed>
     */
    private function attributesFrom(ListingRequest $request): array
    {
        $attributes = $request->safe()->except([
            'thumbnail',
            'remove_thumbnail',
            'gallery',
            'gallery_captions',
            'gallery_existing',
            'set_specific_days',
            'specific_days',
        ]);

        $attributes['line_up'] = json_encode($this->lineUps->fromForm($request->validated('line_up')));

        return $attributes;
    }

    /**
     * Store any newly uploaded gallery images and return their groups.
     *
     * @return array<int, array<string, mixed>>
     */
    private function addedGalleryImages(ListingRequest $request): array
    {
        $captions = $request->input('gallery_captions', []);

        return collect($request->file('gallery', []))
            ->map(fn ($file, $index) => $this->gallery->add($file, $captions[$index] ?? null))
            ->all();
    }

    /**
     * Split the listing's current gallery into the images to keep, with any
     * edited captions applied, and the images the promoter asked to remove.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function reconcileGallery(ListingRequest $request, Listing $listing): array
    {
        $submitted = collect($request->input('gallery_existing', []))
            ->keyBy(fn (array $group): string => (string) ($group['key'] ?? ''));

        $kept = [];
        $removed = [];

        foreach ($this->gallery->parse($listing->gallery) as $group) {
            $edit = $submitted->get($group['key']);

            // An image the form never mentioned is left exactly as it was.
            if ($edit === null) {
                $kept[] = $group;

                continue;
            }

            if (filter_var($edit['remove'] ?? false, FILTER_VALIDATE_BOOL)) {
                $removed[] = $group;

                continue;
            }

            $caption = $edit['caption'] ?? null;
            $group['attributes']['caption'] = filled($caption) ? $caption : null;
            $kept[] = $group;
        }

        return [$kept, $removed];
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    /**
     * The venues this user may pick from, which for a venue owner is only
     * their own.
     *
     * @return Collection<int, Venue>
     */
    private function venues(User $user, ?Listing $listing = null): Collection
    {
        return $user->listableVenues($listing)
            // Their own building first, then everywhere else by name. Written
            // as a CASE rather than MySQL's <=> so the tests' SQLite agrees.
            ->orderByRaw('CASE WHEN venues.user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->orderBy('name')
            ->get(['id', 'name', 'town', 'user_id'])
            ->map(fn (Venue $venue): array => [
                'id' => $venue->id,
                'name' => $venue->name,
                'town' => $venue->town,
                'owned' => $venue->user_id === $user->id,
            ]);
    }

    /**
     * The promoters this user may put a listing under.
     *
     * @return Collection<int, Promoter>
     */
    private function promoters(User $user, ?Listing $listing = null): Collection
    {
        return $user->listablePromoters($listing)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
