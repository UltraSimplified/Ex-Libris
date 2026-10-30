<?php

namespace App\Services\Bookmarks;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where somebody's saved events are kept: against their account when they
 * are signed in, or in a cookie in their browser when they are not.
 */
interface BookmarkStore
{
    /**
     * The ids of the saved listings.
     *
     * @return list<int>
     */
    public function ids(): array;

    public function add(Listing $listing): void;

    public function remove(Listing $listing): void;

    /**
     * The saved listings, as a query to narrow and order.
     *
     * @return Builder<Listing>
     */
    public function listings(): Builder;
}
