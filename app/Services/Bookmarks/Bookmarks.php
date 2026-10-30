<?php

namespace App\Services\Bookmarks;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The bookmarks for whoever is asking: their account's when they are
 * signed in, their browser's when they are not.
 */
class Bookmarks
{
    public static function for(Request $request): BookmarkStore
    {
        $user = $request->user();

        return $user instanceof User
            ? new AccountBookmarks($user)
            : GuestBookmarks::fromRequest($request);
    }
}
