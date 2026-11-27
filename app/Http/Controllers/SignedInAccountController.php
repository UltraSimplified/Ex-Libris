<?php

namespace App\Http\Controllers;

use App\Support\SignedInAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tells an Office tab who its browser is signed in as now: see
 * SignedInAccount. Asked whenever the Office refuses the tab, and whenever
 * the tab comes back into view.
 *
 * Open to anybody, since a tab whose browser has been signed out needs the
 * answer most, and never kept by a cache, since it changes with the session.
 */
class SignedInAccountController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()
            ->json(SignedInAccount::describe($request))
            ->header('Cache-Control', 'no-store, private');
    }
}
