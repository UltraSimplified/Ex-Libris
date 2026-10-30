<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a gig widget be framed by any website, says so explicitly so a
 * site-wide header added later cannot blank every widget, and keeps the
 * embedded pages out of search results, where they would only compete with
 * the listings pages they copy.
 */
class ServeEmbeds
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', 'frame-ancestors *');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
