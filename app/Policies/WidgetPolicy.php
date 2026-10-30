<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Widget;
use Illuminate\Support\Facades\Gate;

/**
 * Authorises gig widgets.
 *
 * A widget is the venue's or the promoter's, so whoever may edit that may
 * do anything with its widgets, and nobody else. Making one is authorised
 * against the venue or promoter it is for, by the controller.
 *
 * Nova checks the same policy without falling back when a method is
 * missing, so every ability is answered and staff are let through.
 */
class WidgetPolicy
{
    /**
     * Staff who can reach Nova administer every widget, including ones
     * for venues nobody has claimed yet.
     */
    public function before(User $user, string $ability): ?bool
    {
        return Gate::forUser($user)->allows('viewNova') ? true : null;
    }

    /**
     * Anybody running a venue or a promoter has widgets to look at.
     */
    public function viewAny(User $user): bool
    {
        return $user->venues()->exists() || $user->promoters()->exists();
    }

    public function view(User $user, Widget $widget): bool
    {
        return $this->runsOwner($user, $widget);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Widget $widget): bool
    {
        return $this->runsOwner($user, $widget);
    }

    public function delete(User $user, Widget $widget): bool
    {
        return $this->runsOwner($user, $widget);
    }

    public function restore(User $user, Widget $widget): bool
    {
        return false;
    }

    public function forceDelete(User $user, Widget $widget): bool
    {
        return false;
    }

    public function replicate(User $user, Widget $widget): bool
    {
        return false;
    }

    private function runsOwner(User $user, Widget $widget): bool
    {
        return $widget->owner !== null
            && Gate::forUser($user)->allows('update', $widget->owner);
    }
}
