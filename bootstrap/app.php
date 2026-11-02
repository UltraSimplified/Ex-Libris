<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MergeGuestBookmarks;
use App\Http\Middleware\RecordSiteVisit;
use App\Http\Middleware\RequireHttps;
use App\Http\Middleware\ServeEmbeds;
use App\Services\Bookmarks\GuestBookmarks;
use Bugsnag\BugsnagLaravel\OomBootstrapper;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

new OomBootstrapper()->bootstrap();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            /*
             * Gig widgets, framed on other people's sites: their own
             * middleware group with no session or cookies, on the widget
             * host once there is one.
             */
            $embeds = Route::middleware('embed');

            if (filled(config('widgets.domain'))) {
                $embeds->domain(config('widgets.domain'));
            }

            $embeds->group(base_path('routes/embed.php'));

            Route::middleware('calendar')->group(base_path('routes/calendar.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(RequireHttps::class);
        $middleware->preventRequestForgery(except: [
            'ad/*/impression',
        ]);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', GuestBookmarks::COOKIE]);
        $middleware->group('embed', [
            ServeEmbeds::class,
            SubstituteBindings::class,
        ]);
        $middleware->group('calendar', [
            SubstituteBindings::class,
        ]);
        $middleware->web(append: [
            HandleAppearance::class,
            MergeGuestBookmarks::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RecordSiteVisit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * A missing page is part of the site rather than a stack trace, so
         * 404s render through Inertia in the site's own layout - which also
         * means the advert and the mailing list still get their slot.
         *
         * It renders in development too. There is nothing to debug about a
         * 404, and a designed page nobody sees while working on the site is
         * one that quietly rots. Everything else keeps Laravel's handling,
         * debug pages included.
         */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            // A widget that has gone leaves an empty frame, not the site, and a
            // calendar app has no use for a page either.
            if ($request->is('embed/*', 'calendar/*')) {
                return response('', $e->getStatusCode());
            }

            if ($request->is('office/*') || $request->is('nova-api/*') || $request->expectsJson()) {
                return null;
            }

            if (! in_array($e->getStatusCode(), [404, 410], true)) {
                return null;
            }

            return Inertia::render('NotFound')
                ->toResponse($request)
                ->setStatusCode($e->getStatusCode());
        });
    })->create();
