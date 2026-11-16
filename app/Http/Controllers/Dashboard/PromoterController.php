<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\PromoterRequest;
use App\Models\Promoter;
use App\Services\Images\BrandImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets an owner keep their promoters' details right.
 *
 * There is no create or destroy: the office sets a promoter up in Nova and
 * says who owns it, so the dashboard only ever edits what already exists.
 */
class PromoterController extends Controller
{
    public function __construct(
        private BrandImage $images,
    ) {}

    /**
     * Show the promoters this account manages.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Promoter::class);

        $promoters = $request->user()
            ->promoters()
            ->withCount(['listings' => fn ($query) => $query->current()])
            ->orderBy('name')
            ->get();

        return Inertia::render('dashboard/promoters/Index', [
            'promoters' => $promoters,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Show the form for editing the given promoter.
     */
    public function edit(Promoter $promoter): Response
    {
        Gate::authorize('update', $promoter);

        return Inertia::render('dashboard/promoters/Edit', [
            'promoter' => $promoter,
        ]);
    }

    /**
     * Update the given promoter.
     */
    public function update(PromoterRequest $request, Promoter $promoter): RedirectResponse
    {
        Gate::authorize('update', $promoter);

        $previous = ['logo' => $promoter->logo, 'banner' => $promoter->banner];

        $promoter->fill($request->safe()->except([
            'logo',
            'banner',
            'remove_logo',
            'remove_banner',
        ]));

        if ($request->hasFile('logo')) {
            $promoter->logo = $this->images->logo($request->file('logo'));
        } elseif ($request->boolean('remove_logo')) {
            $promoter->logo = null;
        }

        if ($request->hasFile('banner')) {
            $promoter->banner = $this->images->banner($request->file('banner'));
        } elseif ($request->boolean('remove_banner')) {
            $promoter->banner = null;
        }

        $promoter->save();

        // The pictures they replaced only go once the row pointing at the
        // new ones has saved, so a failure here leaves nothing broken.
        foreach ($previous as $field => $filename) {
            if ($promoter->wasChanged($field)) {
                $this->images->delete($filename);
            }
        }

        return to_route('dashboard.promoters.index')
            ->with('status', 'Promoter updated.');
    }
}
