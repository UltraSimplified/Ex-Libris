<?php

namespace App\Services\Bookmarks;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bookmarks kept against an account, in the bookmarks table.
 */
class AccountBookmarks implements BookmarkStore
{
    public function __construct(private User $user) {}

    /**
     * Every saved id, finished events included: bookmarks:prune clears
     * those overnight.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return $this->user->bookmarks()->pluck('listings.id')->all();
    }

    public function add(Listing $listing): void
    {
        $this->user->bookmarks()->syncWithoutDetaching([$listing->id]);
    }

    public function remove(Listing $listing): void
    {
        $this->user->bookmarks()->detach($listing->id);
    }

    /**
     * Record whether the account is going to an event it has saved, and say
     * whether it had saved it. Asked of the bookmark itself: an update that
     * changes nothing reports no rows, which says nothing about whether the
     * bookmark is there.
     */
    public function setAttending(Listing $listing, bool $attending): bool
    {
        if (! $this->user->bookmarks()->whereKey($listing->getKey())->exists()) {
            return false;
        }

        $this->user->bookmarks()->updateExistingPivot($listing->getKey(), ['attending' => $attending]);

        return true;
    }

    /**
     * Merge in bookmarks made before signing in. Ones already saved are
     * left as they are.
     *
     * @param  list<int>  $ids
     */
    public function addMany(array $ids): void
    {
        $this->user->bookmarks()->syncWithoutDetaching($ids);
    }

    /**
     * @return Builder<Listing>
     */
    public function listings(): Builder
    {
        return Listing::query()->whereHas(
            'bookmarkedBy',
            fn (Builder $query): Builder => $query->whereKey($this->user->getKey()),
        );
    }
}
