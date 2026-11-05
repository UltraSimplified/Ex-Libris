<?php

namespace App\Http\Middleware;

use App\Models\SiteVisit;
use App\Support\Crawler;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts readers for the Office's unique visits panel, once the page has
 * gone out so that nobody waits on it.
 */
class RecordSiteVisit
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * A failed write is reported and goes no further: a visit is not worth
     * an error for the visitor.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $this->isReaderOnPage($request, $response)) {
            return;
        }

        try {
            SiteVisit::record($request);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * A page reached by someone other than a crawler or the Office's staff.
     * Inertia's page changes count alongside full page loads, but redirects,
     * missing pages and JSON endpoints do not.
     */
    private function isReaderOnPage(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $response->isSuccessful()
            && $this->isPage($response)
            && ! $request->is('office', 'office/*', 'nova-api/*', 'nova-vendor/*')
            && ! Crawler::sent($request)
            && ! $this->isOfficeStaff($request);
    }

    /**
     * The Office's own people, who read the public site all day while they
     * work on it and would otherwise be its most devoted readers.
     */
    private function isOfficeStaff(Request $request): bool
    {
        return Gate::forUser($request->user())->allows('viewNova');
    }

    private function isPage(Response $response): bool
    {
        return $response->headers->has('X-Inertia')
            || str_starts_with((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
