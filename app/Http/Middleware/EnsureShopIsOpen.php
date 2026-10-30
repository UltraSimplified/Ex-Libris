<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the basket and checkout out of sight until the shop is open for
 * business. The catalogue can be browsed before then; nothing can be put in
 * a basket or paid for.
 */
class EnsureShopIsOpen
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('shop.checkout_enabled'), 404);

        return $next($request);
    }
}
