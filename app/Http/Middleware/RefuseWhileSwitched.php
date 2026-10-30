<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nova\Contracts\ImpersonatesUsers;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps staff who have switched to somebody's account away from what only
 * its owner should change: its password, its two-factor login, and whether
 * it exists at all.
 *
 * Most of these ask for the owner's password anyway, but a clear refusal
 * beats a form staff cannot complete.
 */
class RefuseWhileSwitched
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            app(ImpersonatesUsers::class)->impersonating($request),
            403,
            'Switch back to your own account to change this.',
        );

        return $next($request);
    }
}
