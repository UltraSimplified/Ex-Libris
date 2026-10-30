<?php

namespace App\Http\Controllers;

use App\Models\User;
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
 */
class SwitchBackController extends Controller
{
    public function __invoke(Request $request, ImpersonatesUsers $impersonator): Response
    {
        if (! $impersonator->impersonating($request)) {
            return to_route('dashboard');
        }

        $switchedTo = $request->user();

        $impersonator->stopImpersonating($request, Auth::guard(), User::class);

        $request->session()->regenerate();

        // The Office is not an Inertia page, so leave the site for it.
        return Inertia::location(Nova::url('/resources/users/'.$switchedTo->getKey()));
    }
}
