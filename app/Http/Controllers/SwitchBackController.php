<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SignedInAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Nova\Contracts\ImpersonatesUsers;
use Laravel\Nova\Nova;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hands staff back their own account after switching to somebody else's
 * from the Office, and returns them to that account's page there.
 *
 * Nova has its own way back, but it sits behind the Office door, which the
 * account switched to cannot open; this one sits on the site.
 *
 * An Office tab left open while the switch was made asks for JSON instead:
 * it wants its own account back without leaving the form it is holding, so
 * it is told who the browser is signed in as afterwards and goes nowhere.
 */
class SwitchBackController extends Controller
{
    public function __invoke(Request $request, ImpersonatesUsers $impersonator): Response
    {
        if (! $impersonator->impersonating($request)) {
            return $request->wantsJson()
                ? $this->signedInAs($request)
                : to_route('dashboard');
        }

        $switchedTo = $request->user();

        $impersonator->stopImpersonating($request, Auth::guard(), User::class);

        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return $this->signedInAs($request);
        }

        // The Office is not an Inertia page, so leave the site for it.
        return Inertia::location(Nova::url('/resources/users/'.$switchedTo->getKey()));
    }

    /**
     * Who the browser is signed in as afterwards, for an Office tab deciding
     * whether it can carry on: see SignedInAccount.
     */
    private function signedInAs(Request $request): Response
    {
        return response()->json(SignedInAccount::describe($request));
    }
}
