<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Gate;

/**
 * Authorises venue management from the dashboard.
 *
 * The same bargain as PromoterPolicy: the office decides who owns a venue,
 * and the owner may then keep its details right.
 */
class VenuePolicy
{
    /**
     * Staff who can reach Nova administer every venue.
     */
    public function before(User $user, string $ability): ?bool
    {
        return Gate::forUser($user)->allows('viewNova') ? true : null;
    }

    /**
     * An owner may list the venues they manage.
     */
    public function viewAny(User $user): bool
    {
        return $user->venues()->exists();
    }

    public function view(User $user, Venue $venue): bool
    {
        return $this->owns($user, $venue);
    }

    /**
     * A promoter putting an event on somewhere the magazine has never
     * listed adds the place themselves rather than waiting on the office.
     * It goes in unowned: adding a venue is not a claim to run it.
     */
    public function create(User $user): bool
    {
        return $user->promoters()->exists();
    }

    public function update(User $user, Venue $venue): bool
    {
        return $this->owns($user, $venue);
    }

    public function delete(User $user, Venue $venue): bool
    {
        return false;
    }

    public function replicate(User $user, Venue $venue): bool
    {
        return false;
    }

    /**
     * A venue with no owner belongs to the magazine.
     */
    private function owns(User $user, Venue $venue): bool
    {
        return $venue->user_id !== null && $venue->user_id === $user->id;
    }
}
