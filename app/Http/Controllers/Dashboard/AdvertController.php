<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What an account is running, or has run, in the magazine.
 *
 * A stub. Adverts are booked and built by the office in Nova and are not
 * yet tied to an account - adverts.owner is a name, not a link - so there
 * is nothing here to list.
 *
 * Held to promoter and venue owners, who are the only people who
 * advertise, with staff let through as they are everywhere else so they
 * can see what an advertiser sees.
 */
class AdvertController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->managesListings() || Gate::allows('viewNova'),
            403
        );

        return Inertia::render('dashboard/adverts/Index');
    }
}
