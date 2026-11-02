<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard's front page: the account itself, each venue and promoter it
 * manages with how many of their events are on the site, then the events it
 * has saved.
 *
 * Only accounts with something to see get this page - venue managers,
 * promoters and writers, staff holding every role, and anybody with a saved
 * event still to come. Anybody else has nothing here yet, so they are sent
 * to the home page. Logging in and registering both aim here, so that is
 * where they leave a reader too.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->canOpenDashboard()) {
            return to_route('home');
        }

        return Inertia::render('Dashboard', [
            'account' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'venues' => $this->withEventsListed($user->venues())
                ->get()
                ->map(fn (Venue $venue): array => [
                    'id' => $venue->id,
                    'name' => (string) $venue->name,
                    'eventsListed' => (int) $venue->listings_count,
                    'widgets' => (int) $venue->widgets_count,
                ])
                ->all(),
            'promoters' => $this->withEventsListed($user->promoters())
                ->get()
                ->map(fn (Promoter $promoter): array => [
                    'id' => $promoter->id,
                    'name' => (string) $promoter->name,
                    'eventsListed' => (int) $promoter->listings_count,
                    'widgets' => (int) $promoter->widgets_count,
                    'logo' => $promoter->logo,
                ])
                ->all(),
            'bookmarkedListings' => $this->bookmarkedListings($user),
        ]);
    }

    /**
     * The events this account has saved that have not finished, in the same
     * order as the listings pages, with what the panel draws under each.
     *
     * @return Collection<int, Listing>
     */
    private function bookmarkedListings(User $user): Collection
    {
        return $user->bookmarks()
            ->with(['venue', 'category', 'promoter'])
            ->current()
            ->finishingSoonest()
            ->get()
            ->each->append(['ticket_link', 'is_free']);
    }

    /**
     * Each venue or promoter by name, with a count of its events on the site
     * now (not yet over) and of the gig widgets it has made for
     * its own website.
     *
     * @template TModel of Venue|Promoter
     *
     * @param  HasMany<TModel, User>  $relation
     * @return HasMany<TModel, User>
     */
    private function withEventsListed(HasMany $relation): HasMany
    {
        return $relation
            ->withCount(['listings' => fn (Builder $query) => $query->current(), 'widgets'])
            ->orderBy('name');
    }
}
