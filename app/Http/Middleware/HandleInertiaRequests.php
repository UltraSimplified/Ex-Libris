<?php

namespace App\Http\Middleware;

use App\Models\ShopCategory;
use App\Models\User;
use App\Services\Adverts\AdvertPool;
use App\Services\Adverts\AdvertRotation;
use App\Services\Bookmarks\Bookmarks;
use App\Services\Shop\Basket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            /*
             * The sponsorship for the panel at the foot of the page, or null
             * when none is live and the panel asks for one instead.
             */
            'advert' => fn () => AdvertRotation::for(AdvertPool::Sponsorship)->next($request),

            /*
             * The live in-list adverts and how often the listings pages
             * break for one of them. The slots take them in turn, starting
             * after listingAdvert, so the slots share out evenly from one
             * view to the next. Only the pages with slots move that turn on.
             */
            'liveAdverts' => fn () => AdvertRotation::for(AdvertPool::InList)->live(),
            'listingAdvert' => fn () => $this->hasAdvertSlots($request)
                ? AdvertRotation::for(AdvertPool::InList)->next($request)
                : null,
            'advertEvery' => fn () => max(0, (int) config('adverts.listings_every')),
            'auth' => [
                'user' => $request->user(),

                /*
                 * What this account runs. The sidebar heads a section with
                 * each one and hangs that promoter's or venue's listings
                 * underneath, so it needs their names rather than only
                 * whether there are any. Closures so the queries only run
                 * for a page that reads them, and only for somebody signed
                 * in; named columns so an accessor added later cannot
                 * quietly widen what every page carries.
                 */
                'promoters' => fn (): array => $this->named($request->user()?->promoters()),
                'venues' => fn (): array => $this->named($request->user()?->venues()),

                /*
                 * Whether there is anything behind Orders: an order this
                 * account has paid for, whatever has happened to it since.
                 * One that was never paid for does not count, so nobody is
                 * offered a page of abandoned checkouts.
                 */
                'hasOrders' => fn (): bool => (bool) $request->user()?->orders()->paidFor()->exists(),

                /*
                 * What the office has granted this account, with staff
                 * holding all of it. What is already owned and what is
                 * permitted are separate questions: somebody can be a
                 * writer before their first article is published.
                 */
                'roles' => fn (): array => $request->user()?->roles()
                    ?? array_fill_keys(User::ROLES, false),

                /*
                 * Where the office is, for the people allowed in it, and
                 * null for everybody else. Taken from Nova's own config so
                 * the sidebar cannot drift from the door.
                 */
                'office' => fn (): ?string => Gate::forUser($request->user())->allows('viewNova')
                    ? '/'.ltrim((string) config('nova.path'), '/')
                    : null,

                /*
                 * Who switched to this account from the Office, while they
                 * are in it, so every page can say so and offer the way
                 * back; null the rest of the time.
                 */
                'switchedFrom' => fn (): ?string => $request->hasSession() && $request->session()->has('nova_impersonated_by')
                    ? User::find($request->session()->get('nova_impersonated_by'))?->name
                    : null,
            ],

            /*
             * The events this visitor has saved, as ids alone: from their
             * account when signed in, from their browser's cookie when not.
             * Every listing on every page needs to know whether its heart
             * is filled, and a set of ids is cheaper to carry than asking
             * per row.
             */
            'bookmarks' => fn (): array => Bookmarks::for($request)->ids(),

            /*
             * How many things are in this visitor's basket, for the count by
             * the Shop link; null while the shop is not yet open for
             * business, which also tells a product page to keep its Add to
             * basket button out of sight.
             */
            'basket' => fn (): ?array => config('shop.checkout_enabled') && $request->hasSession()
                ? ['count' => app(Basket::class)->count()]
                : null,
            'cdnUrl' => rtrim((string) config('filesystems.disks.do.url'), '/'),

            /*
             * The shop's shelves for the sub-nav under Shop: those published
             * with something on sale, in the office's order. Names and
             * slugs alone, which is all a link needs.
             */
            'shopCategories' => fn (): array => ShopCategory::query()
                ->published()
                ->whereHas('products', fn ($products) => $products->onSale())
                ->ordered()
                ->get(['name', 'slug'])
                ->map(fn (ShopCategory $category): array => ['name' => $category->name, 'slug' => $category->slug])
                ->all(),
        ];
    }

    /**
     * The id and name of everything in the given relation, by name.
     *
     * @param  HasMany<Model, User>|null  $relation
     * @return array<int, array{id: int, name: string}>
     */
    private function named(?HasMany $relation): array
    {
        return $relation
            ?->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Model $record): array => [
                'id' => $record->getKey(),
                'name' => (string) $record->name,
            ])
            ->all() ?? [];
    }

    /**
     * Whether this page sets in-list adverts among its rows: the listings,
     * the features and the stockists. A single listing or feature under the
     * same paths has no slots, but only moves the in-list turn on by one,
     * which costs nothing that is reported.
     */
    private function hasAdvertSlots(Request $request): bool
    {
        return $request->is('listings', 'listings/*', 'magazine/features', 'magazine/features/*', 'magazine/stockists');
    }
}
