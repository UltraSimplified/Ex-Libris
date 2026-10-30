<?php

namespace App\Http\Middleware;

use App\Http\Controllers\SwitchBackController;
use Closure;
use Illuminate\Http\Request;
use Laravel\Nova\Contracts\ImpersonatesUsers;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logging out of an account staff switched to hands them back their own,
 * rather than signing them out altogether.
 *
 * Sits on Fortify's routes, so it only has the logout to watch for.
 */
class SwitchBackOnLogout
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('logout') && app(ImpersonatesUsers::class)->impersonating($request)) {
            return app()->call(SwitchBackController::class, ['request' => $request]);
        }

        return $next($request);
    }
}
