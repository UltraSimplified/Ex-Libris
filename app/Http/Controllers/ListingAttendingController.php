<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\Bookmarks\AccountBookmarks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The tick under a bookmarked listing on the Dashboard: tickets bought, or
 * going if the event is free.
 *
 * Two verbs rather than a toggle, as with the bookmark itself, so a double
 * tap or a flaky connection cannot leave it the wrong way round.
 */
class ListingAttendingController extends Controller
{
    public function store(Request $request, Listing $listing): JsonResponse
    {
        return $this->record($request, $listing, true);
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        return $this->record($request, $listing, false);
    }

    /**
     * There is nowhere to record it for an event the account has not saved.
     */
    private function record(Request $request, Listing $listing, bool $attending): JsonResponse
    {
        abort_unless((new AccountBookmarks($request->user()))->setAttending($listing, $attending), 404);

        return response()->json(['attending' => $attending]);
    }
}
