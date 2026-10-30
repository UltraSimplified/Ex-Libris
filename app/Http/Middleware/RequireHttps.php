<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the live site on HTTPS whatever the web server is configured to do:
 * a plain request is sent to its https address, and a secure response tells
 * the browser never to ask over plain HTTP again. The site itself, the
 * Dashboard and the Office all pass through here.
 *
 * Only production is held to it, so the site still runs over plain HTTP
 * locally and in tests.
 */
class RequireHttps
{
    /**
     * How long browsers should remember to use HTTPS: a year, in seconds.
     */
    public const HSTS_MAX_AGE = 31536000;

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction()) {
            return $next($request);
        }

        if (! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), $request->isMethodSafe() ? 301 : 308);
        }

        $response = $next($request);

        $response->headers->set('Strict-Transport-Security', 'max-age='.self::HSTS_MAX_AGE);

        return $response;
    }
}
