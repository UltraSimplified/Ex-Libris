<?php

use App\Http\Controllers\AdvertClickController;
use App\Http\Controllers\AdvertEventController;
use App\Http\Controllers\AdvertReportController;
use App\Http\Controllers\BasketController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ClaimOrderController;
use App\Http\Controllers\Dashboard\AdvertController as DashboardAdvertController;
use App\Http\Controllers\Dashboard\ArticleController as DashboardArticleController;
use App\Http\Controllers\Dashboard\HomeController as DashboardHomeController;
use App\Http\Controllers\Dashboard\ListingController as DashboardListingController;
use App\Http\Controllers\Dashboard\OrderController as DashboardOrderController;
use App\Http\Controllers\Dashboard\PromoterController as DashboardPromoterController;
use App\Http\Controllers\Dashboard\VenueController as DashboardVenueController;
use App\Http\Controllers\Dashboard\WidgetController as DashboardWidgetController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\FeatureReadController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GuestOrderController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ListingAttendingController;
use App\Http\Controllers\ListingBookmarkController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\ListingExportController;
use App\Http\Controllers\PackingSlipController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SwitchBackController;
use App\Http\Controllers\WriterController;
use App\Http\Controllers\WriterStatusController;
use App\Http\Middleware\EnsureShopIsOpen;
use App\Http\Middleware\SwitchBackOnLogout;
use App\Models\Feature;
use App\Models\Issue;
use App\Support\SiteMeta;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

/* handle ad clicks */
Route::post('/ad/{advert}/impression', [AdvertEventController::class, 'impression'])
    ->middleware('throttle:60,1');

Route::get('/ad/{advert}/click', AdvertClickController::class)
    ->name('advert.click')
    ->middleware('throttle:60,1');

/*
 * The reading time a feature page reports as its reader leaves, by beacon.
 * Like the impression beacon it carries no CSRF token: it can only add to
 * anonymous totals, and the throttle keeps that within reason.
 */
Route::post('/magazine/features/{feature}/reading', FeatureReadController::class)
    ->middleware('throttle:60,1')
    ->name('features.reading');

/*
 * The home page leads with the features picked for it, or the newest if
 * none are, and lists the next newest below without repeating them.
 */
Route::get('/', function () {
    $issue = Issue::orderby('created_at', 'desc')->first();
    $featured = Feature::leading();

    $features = Feature::visible()
        ->whereKeyNot($featured->modelKeys())
        ->orderByDesc('created_at')
        ->withByline()
        ->with('tag')
        ->limit(3)
        ->get();

    return Inertia::render('Welcome', [
        'meta' => SiteMeta::home()->toArray(),
        'issue' => $issue,
        'featured' => $featured,
        'features' => $features,
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

/*
 * The Office's preview of a feature, framed behind the eye on the features
 * list. Declared above the public feature route, which would otherwise
 * take "preview" for a slug and show the page with the site around it.
 */
Route::get('/magazine/features/{feature}/preview', [FeatureController::class, 'preview'])
    ->middleware(['auth', 'can:viewNova'])
    ->name('features.preview');

Route::get('/magazine/features/{id}/{slug}', [FeatureController::class, 'show'])->name('feature');

Route::get('/listings/dates/{start}/{end}', [ListingController::class, 'dates'])
    ->where(['start' => '\d{4}-\d{2}-\d{2}', 'end' => '\d{4}-\d{2}-\d{2}']);

Route::get('listings/category/{slug}', [ListingController::class, 'by_category'])->name('Category');

/*
 * A listing on its own, framed behind the eye in the Office and the
 * dashboard's Preview. Declared above the public listing route, which
 * would otherwise match it with slug "preview".
 */
Route::get('listings/{listing}/preview', [ListingController::class, 'preview'])
    ->middleware(['auth'])
    ->name('listings.preview');

// Posted to by the Active writer tickbox on a writer's page in the Office.
Route::put('writers/{user}/active', WriterStatusController::class)
    ->middleware(['auth'])
    ->name('writers.active');

Route::get('listings/{id}/{slug}', [ListingController::class, 'listing'])->name('Listing');

Route::get('listings/venue/{id}/{slug}', [ListingController::class, 'venue'])->name('Venue');

Route::get('listings/promoter/{id}/{slug}', [ListingController::class, 'promoter'])->name('Promoter');

/*
 * Bookmarks, for everybody: an account keeps them in the bookmarks table
 * and anybody else in a cookie. Saving one is a verb apiece rather than a
 * toggle, so asking twice cannot leave the heart out of step.
 */
Route::get('listings/bookmarked', [ListingController::class, 'bookmarked'])
    ->name('listings.bookmarked');

Route::middleware('throttle:60,1')->group(function (): void {
    Route::post('listings/{listing}/bookmark', [ListingBookmarkController::class, 'store'])
        ->name('listings.bookmark.store');

    Route::delete('listings/{listing}/bookmark', [ListingBookmarkController::class, 'destroy'])
        ->name('listings.bookmark.destroy');
});

/*
 * The tick under a bookmarked listing on the Dashboard. Accounts only: a
 * bookmark kept in a browser's cookie has nowhere to record it.
 */
Route::middleware(['auth', 'throttle:60,1'])->group(function (): void {
    Route::put('listings/{listing}/attending', [ListingAttendingController::class, 'store'])
        ->name('listings.attending.store');

    Route::delete('listings/{listing}/attending', [ListingAttendingController::class, 'destroy'])
        ->name('listings.attending.destroy');
});

Route::get('listings/', [ListingController::class, 'all'])->name('Listings');

// All was at listings/all until it became the listings home page.
Route::permanentRedirect('listings/all', '/listings');

Route::get('listings/tomorrow', [ListingController::class, 'tomorrow'])->name('Tomorrow');

Route::get('listings/thisweek', [ListingController::class, 'thisweek'])->name('ThisWeek');

Route::get('listings/nextweek', [ListingController::class, 'nextweek'])->name('NextWeek');

Route::get('listings/today', [ListingController::class, 'today'])->name('today');

Route::get('shop', [ShopController::class, 'index'])->name('Shop');

Route::get('shop/{shopCategory:slug}', [ShopController::class, 'category'])->name('shop.category');

/*
 * A product is found only on its own shelf, so an old link to a product
 * since moved to another category is not found rather than shown at the
 * wrong address.
 */
Route::get('shop/{shopCategory:slug}/{product:slug}', [ShopController::class, 'product'])
    ->scopeBindings()
    ->name('shop.product');

/*
 * The basket, kept in the session so nobody need sign in to fill one, and
 * hidden until shop.checkout_enabled opens the shop for business.
 */
Route::middleware(EnsureShopIsOpen::class)->group(function () {
    Route::get('basket', [BasketController::class, 'show'])->name('basket.show');
    Route::post('basket/items', [BasketController::class, 'store'])->name('basket.items.store');
    Route::patch('basket/items/{variant}', [BasketController::class, 'update'])->name('basket.items.update');
    Route::delete('basket/items/{variant}', [BasketController::class, 'destroy'])->name('basket.items.destroy');
    Route::put('basket/fulfilment', [BasketController::class, 'fulfilment'])->name('basket.fulfilment');

    Route::post('checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('checkout.store');
    Route::get('checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('checkout/cancelled', [CheckoutController::class, 'cancelled'])->name('checkout.cancelled');
});

/*
 * An order as its customer sees it, from the signed link in their emails, and
 * only for its account if it has one. Outside the auth group so a guest can
 * still follow theirs, and outside the shop's opening switch so links already
 * sent keep working.
 */
Route::get('orders/{order:reference}', [GuestOrderController::class, 'show'])
    ->middleware('signed')
    ->name('orders.show');

/*
 * Adds a guest's order to an account, from the signed link in its
 * confirmation email. Anyone not signed in signs in or registers first.
 */
Route::get('orders/{order:reference}/claim', ClaimOrderController::class)
    ->middleware(['auth', 'signed'])
    ->name('orders.claim');

Route::get('magazine/features', [FeatureController::class, 'index'])->name('Features');

Route::get('magazine/features/{tag}', [FeatureController::class, 'tag'])
    ->where('tag', '[a-z0-9-]+')
    ->name('features.tag');

Route::get('magazine/archives/{year}', [IssueController::class, 'archives'])->name('Archive Year');
Route::get('magazine/archives', [IssueController::class, 'archives'])->name('Archives');

Route::get('magazine/stockists', [IssueController::class, 'stockists']);

/*
 * The account page, for every account once its address is verified. Logging
 * in and verifying both land here.
 */
Route::get('dashboard', DashboardHomeController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

/*
 * Fortify's own register route with a limit added, which it has no setting
 * for. Loaded after Fortify's, so this is the one that answers.
 */
Route::post('register', [RegisteredUserController::class, 'store'])
    ->middleware([SwitchBackOnLogout::class, 'guest:web', 'throttle:register'])
    ->name('register.store');

/*
 * Staff who switched to somebody's account from the Office take their own
 * back here, and land on that account's page in the Office.
 */
Route::post('switch-back', SwitchBackController::class)
    ->middleware('auth')
    ->name('switch-back');

Route::middleware(['auth', 'verified'])
    ->prefix('dashboard')
    ->name('dashboard.')
    ->group(function () {
        Route::resource('listings', DashboardListingController::class)
            ->except('show');

        Route::post('listings/{listing}/duplicate', [DashboardListingController::class, 'duplicate'])
            ->name('listings.duplicate');

        /*
         * Promoters are created in Nova, which is also where an owner is
         * granted, so the dashboard only lists and edits them.
         */
        Route::resource('promoters', DashboardPromoterController::class)
            ->only(['index', 'edit', 'update']);

        /*
         * Venues too, except that a promoter writing up an event at a hall
         * the magazine has never listed can add it from the listing form.
         */
        Route::resource('venues', DashboardVenueController::class)
            ->only(['index', 'store', 'edit', 'update']);

        /*
         * Gig widgets: a venue's or promoter's events, embedded in their
         * own website. The embeds themselves are in routes/embed.php.
         */
        Route::resource('widgets', DashboardWidgetController::class)
            ->except('show');

        /*
         * Adverts and articles are stubs, holding their place in the
         * interface. Orders lists what the account has bought from the shop.
         */
        Route::get('adverts', [DashboardAdvertController::class, 'index'])->name('adverts.index');
        Route::get('articles', [DashboardArticleController::class, 'index'])->name('articles.index');
        Route::get('orders', [DashboardOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order:reference}', [DashboardOrderController::class, 'show'])->name('orders.show');

        // Bookmarks were a page of their own until they became a panel on the Dashboard.
        Route::permanentRedirect('bookmarks', '/dashboard#bookmarked-listings');
    });

Route::get('/download/{id}', [FileController::class, 'download'])->whereNumber('id');

// Issued as a signed URL by the Nova listings export action.
Route::get('exports/listings', ListingExportController::class)
    ->middleware('signed')
    ->name('exports.listings');

// Issued as a signed URL by the Office's Print packing slips action.
Route::get('exports/packing-slips', PackingSlipController::class)
    ->middleware('signed')
    ->name('exports.packing-slips');

// Issued as a signed URL by the Office's Adverts card and each advert's page.
Route::get('exports/adverts/{advert}/report', AdvertReportController::class)
    ->middleware('signed')
    ->withTrashed()
    ->name('exports.advert-report');

require __DIR__.'/settings.php';

/*
 * A writer's own page, linked from Our Writers by the writer's name.
 */
Route::get('our-writers/{slug}', [WriterController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('writer');

/*
 * PAGES
 *
 * Editable pages are reached by their slug alone, so adding one in Nova puts
 * it live without a route. Registered last, after every other route and the
 * settings file, so it only ever catches what nothing else claimed - and a
 * slug with no page behind it becomes the Page Not Found screen.
 */
Route::get('{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('page');
