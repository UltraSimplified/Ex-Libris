<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

/**
 * Authorises listing management for promoters and venues on the dashboard.
 *
 * Nova checks the same policy, and its authorizedTo() calls the gate
 * directly without falling back when a method is missing — so every
 * ability a promoter can hold has to be answered here, and staff have to
 * be let through explicitly or the policy locks them out of their own
 * back office.
 */
class ListingPolicy
{
    /**
     * Staff who can reach Nova administer every listing, whoever owns it.
     */
    public function before(User $user, string $ability): ?bool
    {
        return Gate::forUser($user)->allows('viewNova') ? true : null;
    }

    /**
     * Any signed-in user may see their own listing index.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the listing.
     */
    public function view(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    /**
     * Only the office and the accounts it has agreed - those it has linked
     * to a promoter or a venue - publish listings. There is no queue: what
     * they save goes straight up, so nobody else may add one.
     */
    public function create(User $user): bool
    {
        return $user->managesListings();
    }

    /**
     * Determine whether the user can update the listing.
     *
     * An edit publishes as surely as a new listing does, so it takes an
     * agreed account too: somebody who once sent one in before the office
     * linked them to anything can still see and delete it, but not change it.
     */
    public function update(User $user, Listing $listing): bool
    {
        return $user->managesListings() && $this->owns($user, $listing);
    }

    /**
     * Determine whether the user can delete the listing.
     */
    public function delete(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    /**
     * Determine whether the user can duplicate the listing.
     *
     * The same right as editing: a copy is only a starting point for
     * something this account was going to be allowed to write anyway.
     */
    public function replicate(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }

    /**
     * Who may edit a listing on the front end.
     *
     * Three ways in: the account that entered it, the promoter putting the
     * event on, and the venue hosting it. The venue is deliberate - they
     * are usually the first to know when a date moves, and they should not
     * have to chase the office to correct it.
     *
     * A listing with none of those set belongs to the magazine, and only
     * Nova can touch it.
     */
    private function owns(User $user, Listing $listing): bool
    {
        return $this->matches($listing->user_id, $user->id)
            || $this->manages($user->promoters(), $listing->promoter_id)
            || $this->manages($user->venues(), $listing->venue_id);
    }

    /**
     * Whether the listing points at a promoter or venue this user holds.
     */
    private function manages(HasMany $owned, ?int $id): bool
    {
        return $id !== null && $owned->whereKey($id)->exists();
    }

    /**
     * Both sides have to be set: two unowned records are not a match.
     */
    private function matches(?int $left, ?int $right): bool
    {
        return $left !== null && $left === $right;
    }
}
