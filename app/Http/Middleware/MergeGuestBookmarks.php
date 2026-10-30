<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Bookmarks\AccountBookmarks;
use App\Services\Bookmarks\GuestBookmarks;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Moves the events somebody saved before signing in onto their account.
 *
 * Done on the first request after signing in rather than in the login
 * itself, so logging in, registering and being remembered all take the
 * same path. The cookie goes once it has been read, so signing out on a
 * shared computer leaves no list behind for the next person.
 */
class MergeGuestBookmarks
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->cookies->has(GuestBookmarks::COOKIE) ? $request->user() : null;

        if ($user instanceof User) {
            (new AccountBookmarks($user))->addMany(GuestBookmarks::fromRequest($request)->ids());
            Cookie::expire(GuestBookmarks::COOKIE);
        }

        return $next($request);
    }
}
