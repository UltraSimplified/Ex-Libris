<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\WidgetRequest;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Models\Widget;
use App\Support\Brand;
use App\Support\Widgets\WidgetDensity;
use App\Support\Widgets\WidgetFont;
use App\Support\Widgets\WidgetSizeMode;
use App\Support\Widgets\WidgetSnippets;
use App\Support\Widgets\WidgetTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a venue or promoter make gig widgets - their events, embedded in
 * their own website - and copy the snippet that shows one. A publisher
 * makes them of the events they have bookmarked.
 *
 * A widget belongs to the venue or promoter, not to the account that made
 * it, so anyone who runs the venue can change it. A publisher's belongs to
 * the publisher.
 */
class WidgetController extends Controller
{
    /**
     * The widgets of every venue and promoter this account runs, and of its
     * own bookmarks, or of the one the sidebar narrowed it to. As with
     * listings, an id the account does not run is ignored rather than
     * filtered on.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Widget::class);

        $user = $request->user();
        $venue = $user->venues()->find($request->integer('venue'), ['id', 'name']);
        $promoter = $user->promoters()->find($request->integer('promoter'), ['id', 'name']);
        $owner = $venue ?? $promoter ?? ($request->boolean('bookmarks') ? $user : null);

        $widgets = Widget::query()
            ->with('owner:id,name')
            ->where(function (Builder $query) use ($user, $owner): void {
                if ($owner) {
                    $query->where('owner_type', $owner::class)->where('owner_id', $owner->id);

                    return;
                }

                $query->where(fn (Builder $query) => $query
                    ->where('owner_type', Venue::class)
                    ->whereIn('owner_id', $user->venues()->select('id')))
                    ->orWhere(fn (Builder $query) => $query
                        ->where('owner_type', Promoter::class)
                        ->whereIn('owner_id', $user->promoters()->select('id')))
                    ->orWhere(fn (Builder $query) => $query
                        ->where('owner_type', User::class)
                        ->where('owner_id', $user->id));
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Widget $widget): array => [
                'id' => $widget->id,
                'name' => $widget->name,
                'owner' => $this->ownerLabel($widget),
                'template' => $widget->template->label(),
                'density' => $widget->density->label(),
                'active' => $widget->active,
            ]);

        return Inertia::render('dashboard/widgets/Index', [
            'widgets' => $widgets,
            'venue' => $venue?->only(['id', 'name']),
            'promoter' => $promoter?->only(['id', 'name']),
            'forBookmarks' => $owner instanceof User,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Start a widget: which venue or promoter it is for, or bookmarks, and
     * how it looks.
     * The rest is set on the next page, with a preview beside it.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Widget::class);

        $owners = $this->owners($request->user());

        $asked = match (true) {
            $request->filled('venue') => 'venue:'.$request->integer('venue'),
            $request->filled('promoter') => 'promoter:'.$request->integer('promoter'),
            $request->boolean('bookmarks') => 'bookmarks',
            default => null,
        };

        return Inertia::render('dashboard/widgets/Create', [
            'owners' => $owners,
            'owner' => collect($owners)->firstWhere('value', $asked)['value'] ?? ($owners[0]['value'] ?? null),
            'templates' => $this->choices(WidgetTemplate::cases()),
            'densities' => $this->choices(WidgetDensity::cases()),
        ]);
    }

    public function store(WidgetRequest $request): RedirectResponse
    {
        Gate::authorize('create', Widget::class);

        $owner = $this->resolveOwner($request->validated('owner'), $request->user());
        Gate::authorize('createFor', [Widget::class, $owner]);

        $widget = new Widget($request->widgetAttributes());
        $widget->owner()->associate($owner);
        $widget->user()->associate($request->user());
        $widget->save();

        return to_route('dashboard.widgets.edit', $widget)
            ->with('status', 'Widget made. Copy the snippet below into your website.');
    }

    public function edit(Request $request, Widget $widget): Response
    {
        Gate::authorize('update', $widget);

        $widget->load('owner');

        return Inertia::render('dashboard/widgets/Edit', [
            'widget' => [
                ...$widget->only(['id', 'key', 'name', 'width', 'height', 'active']),
                'owner' => $this->ownerLabel($widget),
                'for_promoter' => $widget->isForPromoter(),
                'for_bookmarks' => $widget->isForBookmarks(),
                'spans_venues' => $widget->spansVenues(),
                'template' => $widget->template->value,
                'density' => $widget->density->value,
                'font' => $widget->font->value,
                'size_mode' => $widget->size_mode->value,
                'options' => $widget->settings()->toArray(),
                'use_brand' => blank($widget->colours),
                'colours' => $widget->colours ?? array_fill_keys(Brand::COLUMNS, null),
            ],
            'ownerColours' => $widget->isForBookmarks() ? null : $widget->owner?->only(Brand::COLUMNS),
            'ownerEditUrl' => match (true) {
                $widget->owner instanceof Venue => route('dashboard.venues.edit', $widget->owner),
                $widget->owner instanceof Promoter => route('dashboard.promoters.edit', $widget->owner),
                default => null,
            },
            'snippets' => WidgetSnippets::for($widget),
            'hostReady' => WidgetSnippets::hostReady(),
            'embedUrl' => route('widgets.embed', ['widget' => $widget->key]),
            'clicks' => (int) $widget->dailyStats()->where('date', '>=', today()->subDays(27))->sum('clicks'),
            'templates' => $this->choices(WidgetTemplate::cases()),
            'densities' => $this->choices(WidgetDensity::cases()),
            'fonts' => $this->choices(WidgetFont::cases()),
            'sizeModes' => $this->choices(WidgetSizeMode::cases()),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(WidgetRequest $request, Widget $widget): RedirectResponse
    {
        Gate::authorize('update', $widget);

        $widget->update($request->widgetAttributes());

        return to_route('dashboard.widgets.edit', $widget)
            ->with('status', 'Widget saved. Sites showing it will update within a couple of minutes.');
    }

    public function destroy(Widget $widget): RedirectResponse
    {
        Gate::authorize('delete', $widget);

        $widget->delete();

        return to_route('dashboard.widgets.index')
            ->with('status', 'Widget deleted. Anywhere it was pasted now shows nothing.');
    }

    /**
     * The venues and promoters this account may make widgets for, and its
     * bookmarks when it is a publisher.
     *
     * @return list<array{value: string, label: string}>
     */
    private function owners(User $user): array
    {
        return [
            ...($user->hasRole('is_publisher') ? [['value' => 'bookmarks', 'label' => 'My bookmarks']] : []),
            ...$user->venues()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Venue $venue): array => ['value' => 'venue:'.$venue->id, 'label' => $venue->name.' (venue)']),
            ...$user->promoters()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Promoter $promoter): array => ['value' => 'promoter:'.$promoter->id, 'label' => $promoter->name.' (promoter)']),
        ];
    }

    private function resolveOwner(string $owner, User $user): Model
    {
        if ($owner === 'bookmarks') {
            return $user;
        }

        [$type, $id] = explode(':', $owner, 2);

        return ($type === 'promoter' ? Promoter::query() : Venue::query())->findOrFail((int) $id);
    }

    /**
     * What a widget shows, as the dashboard names it: the venue or
     * promoter, or the publisher's bookmarks.
     */
    private function ownerLabel(Widget $widget): ?string
    {
        return $widget->isForBookmarks() ? 'My bookmarks' : $widget->owner?->name;
    }

    /**
     * @param  array<int, WidgetTemplate|WidgetDensity|WidgetFont|WidgetSizeMode>  $cases
     * @return list<array{value: string, label: string, description: ?string}>
     */
    private function choices(array $cases): array
    {
        return array_map(fn ($case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'description' => method_exists($case, 'description') ? $case->description() : null,
        ], $cases);
    }
}
