<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Contracts\ImpersonatesUsers;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers an Office tab left open while its browser was switched to
 * somebody's account in another tab.
 *
 * The account switched to cannot use the Office, and Nova meets a refusal
 * from any of its own calls by leaving the page for its 403 screen, taking
 * whatever was being written there with it. Nova fetches its notifications
 * every seven seconds, so a feature half-written in one tab was lost moments
 * after switching in another, without its Update button ever being pressed.
 *
 * Locked is still a refusal, but Nova does not leave the page for it: the
 * form stays as it was, and office-unsaved-work.js offers the way back to
 * the account it was opened in. Opening an Office page while switched is
 * still refused outright, since there is nothing on it yet to lose.
 */
class HoldOfficeWhileSwitched
{
    public function __construct(private ImpersonatesUsers $impersonator) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isCallFromHeldTab($request)) {
            return $next($request);
        }

        $switchedTo = $request->user()?->name;

        return response()->json([
            'message' => "This browser is in {$switchedTo}'s account now, so the Office cannot do this as you. Switch back to carry on.",
            'switchedTo' => $switchedTo,
            'switchBackUrl' => route('switch-back', absolute: false),
        ], Response::HTTP_LOCKED);
    }

    /**
     * One of Nova's own calls from a page already open, rather than a page
     * being opened, while staff are in an account that cannot use the Office.
     */
    private function isCallFromHeldTab(Request $request): bool
    {
        return $request->expectsJson()
            && ! $request->inertia()
            && $this->impersonator->impersonating($request)
            && Gate::denies('viewNova');
    }
}
