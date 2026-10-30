<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\Bookmarks\Bookmarks;
use App\Services\Bookmarks\BookmarkStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saving an event to come back to: against the account for somebody signed
 * in, in a cookie for everybody else.
 *
 * Two verbs rather than a toggle, so a double click or a flaky connection
 * cannot leave the heart the wrong way round: asking twice for the same
 * thing gets the same answer both times.
 */
class ListingBookmarkController extends Controller
{
    public function store(Request $request, Listing $listing): JsonResponse
    {
        $bookmarks = Bookmarks::for($request);
        $bookmarks->add($listing);

        return $this->state($bookmarks, true);
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        $bookmarks = Bookmarks::for($request);
        $bookmarks->remove($listing);

        return $this->state($bookmarks, false);
    }

    /**
     * What the heart should now show, and how many are saved in all - the
     * page keeps its own count in step without asking again.
     */
    private function state(BookmarkStore $bookmarks, bool $bookmarked): JsonResponse
    {
        return response()->json([
            'bookmarked' => $bookmarked,
            'count' => count($bookmarks->ids()),
        ]);
    }
}
