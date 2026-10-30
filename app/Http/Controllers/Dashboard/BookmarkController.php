<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The listings an account has saved to come back to.
 */
class BookmarkController extends Controller
{
    public function index(Request $request): Response
    {
        $listings = $request->user()->bookmarks()
            ->with(['venue', 'category'])
            ->current()
            ->orderBy('start_date')
            ->get();

        return Inertia::render('dashboard/bookmarks/Index', [
            'listings' => $listings,
        ]);
    }
}
