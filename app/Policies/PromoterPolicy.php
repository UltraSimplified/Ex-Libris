<?php

namespace App\Policies;

use App\Models\Promoter;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Authorises promoter management from the dashboard.
 *
 * Ownership is granted in Nova rather than claimed, so an owner may edit
 * what they hold and nothing else: no creating, no deleting, and no
 * reassigning a promoter to somebody else.
 *
 * Nova checks the same policy and calls the gate without falling back when
 * a method is missing, so every ability has to be answered here and staff
 * have to be let through explicitly.
 */
class PromoterPolicy
{
    /**
     * Staff who can reach Nova administer every promoter.
     */
    public function before(User $user, string $ability): ?bool
    {
        return Gate::forUser($user)->allows('viewNova') ? true : null;
    }

    /**
     * An owner may list the promoters they manage.
     */
    public function viewAny(User $user): bool
    {
        return $user->promoters()->exists();
    }

    public function view(User $user, Promoter $promoter): bool
    {
        return $this->owns($user, $promoter);
    }

    /**
     * Promoters are set up by the office, who decide who owns them.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Promoter $promoter): bool
    {
        return $this->owns($user, $promoter);
    }

    public function delete(User $user, Promoter $promoter): bool
    {
        return false;
    }

    public function replicate(User $user, Promoter $promoter): bool
    {
        return false;
    }

    /**
     * A promoter with no owner belongs to the magazine.
     */
    private function owns(User $user, Promoter $promoter): bool
    {
        return $promoter->user_id !== null && $promoter->user_id === $user->id;
    }
}
