<?php

namespace App\Policies;

use App\Models\Festival;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Authorises festival management, from Backstage and the Office.
 *
 * In Backstage an account manages the festivals of the promoters it runs,
 * once the office has given it Runs Festivals - User::managedFestivals()
 * says which. It makes its own, unlike promoters, but cannot hand one to
 * another promoter.
 *
 * Nova checks the same policy and calls the gate without falling back when
 * a method is missing, so every ability has to be answered here and staff
 * have to be let through explicitly. Staff reach every festival in the
 * Office whether or not they run festivals themselves.
 */
class FestivalPolicy
{
    /**
     * Staff who can reach Nova administer every festival, except that
     * nobody replicates one there: Nova's copy would share the festival's
     * picture files, which go when either festival is deleted. Backstage
     * starts a new festival from an earlier one with copies of its own.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'replicate') {
            return null;
        }

        return Gate::forUser($user)->allows('viewNova') ? true : null;
    }

    /**
     * An account may list its festivals once it may make one.
     */
    public function viewAny(User $user): bool
    {
        return $this->create($user);
    }

    public function view(User $user, Festival $festival): bool
    {
        return $this->manages($user, $festival);
    }

    /**
     * Somebody given Runs Festivals, with a promoter to put one on for.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('runs_festivals') && $user->promoters()->exists();
    }

    public function update(User $user, Festival $festival): bool
    {
        return $this->manages($user, $festival);
    }

    public function delete(User $user, Festival $festival): bool
    {
        return $this->manages($user, $festival);
    }

    public function restore(User $user, Festival $festival): bool
    {
        return false;
    }

    public function forceDelete(User $user, Festival $festival): bool
    {
        return false;
    }

    public function replicate(User $user, Festival $festival): bool
    {
        return false;
    }

    private function manages(User $user, Festival $festival): bool
    {
        return $user->managedFestivals()->whereKey($festival->getKey())->exists();
    }
}
