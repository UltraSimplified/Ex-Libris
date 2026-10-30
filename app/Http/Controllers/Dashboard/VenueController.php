<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\VenueRequest;
use App\Models\Venue;
use App\Services\Images\BrandImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets an owner keep their venues' details right.
 *
 * As with promoters, the office creates a venue and assigns its owner, so
 * the dashboard only edits.
 */
class VenueController extends Controller
{
    public function __construct(
        private BrandImage $images,
    ) {}

    /**
     * Show the venues this account manages.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Venue::class);

        $venues = $request->user()
            ->venues()
            ->withCount(['listings' => fn ($query) => $query->current()])
            ->orderBy('name')
            ->get();

        return Inertia::render('dashboard/venues/Index', [
            'venues' => $venues,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Add a venue the magazine has not listed before.
     *
     * Called from inside the listing form, so it answers with JSON rather
     * than redirecting: a promoter halfway through writing up an event
     * should not lose it to add the hall they have booked. The venue goes
     * in unowned - naming a place is not a claim to run it.
     */
    public function store(VenueRequest $request): JsonResponse
    {
        Gate::authorize('create', Venue::class);

        $attributes = $request->validated();

        // Somewhere with this name in this town is the place they mean, so
        // hand it back rather than standing up a second copy of it. Same
        // name in a different town is a different venue and is created.
        $venue = Venue::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($attributes['name'])])
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(COALESCE(town, \'\')) = ?', [mb_strtolower((string) ($attributes['town'] ?? ''))]))
            ->first() ?? Venue::create($attributes);

        return response()->json([
            'id' => $venue->id,
            'name' => $venue->name,
            'town' => $venue->town,
            'owned' => false,
        ], 201);
    }

    /**
     * Show the form for editing the given venue.
     */
    public function edit(Venue $venue): Response
    {
        Gate::authorize('update', $venue);

        return Inertia::render('dashboard/venues/Edit', [
            'venue' => [
                ...$venue->toArray(),
                'description' => $this->descriptionAsText($venue->description),
            ],
            'publicUrl' => route('Venue', ['id' => $venue->id, 'slug' => Str::slug($venue->name)]),
        ]);
    }

    /**
     * Update the given venue.
     */
    public function update(VenueRequest $request, Venue $venue): RedirectResponse
    {
        Gate::authorize('update', $venue);

        $previous = $venue->cover_photo;

        $venue->fill($request->safe()->except(['cover_photo', 'remove_cover_photo', 'description']));

        if ($request->has('description')) {
            $venue->description = $this->descriptionAsHtml($request->validated('description'));
        }

        if ($request->hasFile('cover_photo')) {
            $venue->cover_photo = $this->images->cover($request->file('cover_photo'));
        } elseif ($request->boolean('remove_cover_photo')) {
            $venue->cover_photo = null;
        }

        $venue->save();

        // The picture they replaced only goes once the row pointing at the
        // new one has saved.
        if ($venue->wasChanged('cover_photo')) {
            $this->images->delete($previous);
        }

        return to_route('dashboard.venues.index')
            ->with('status', 'Venue updated.');
    }

    /**
     * The description as an owner types it: the Office writes it in a rich
     * text editor, but the dashboard offers a plain textarea, so paragraphs
     * become blank lines and any other markup is dropped.
     */
    private function descriptionAsText(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $text = preg_replace(['#<br\s*/?>#i', '#</(p|div|h[1-6]|li)>#i'], ["\n", "\n\n"], $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)));
    }

    /**
     * Turn what the owner typed back into the HTML the venue page prints.
     * It is escaped first, so nothing typed can become markup on the public
     * page; blank lines start a paragraph and single ones break a line.
     */
    private function descriptionAsHtml(?string $text): ?string
    {
        $text = trim(str_replace("\r", '', (string) $text));

        if ($text === '') {
            return null;
        }

        return collect(preg_split("/\n{2,}/", $text))
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');
    }
}
