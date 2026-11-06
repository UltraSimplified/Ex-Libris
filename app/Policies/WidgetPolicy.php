<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Authorises gig widgets.
 *
 * A widget is the venue's or the promoter's, so whoever may edit that may
 * do anything with its widgets, and nobody else. A publisher's widget shows
 * their own bookmarks, so is theirs alone. Making one is authorised against
 * what it is for, by createFor.
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
     * Anybody running a venue or a promoter, or made a publisher, has
     * widgets to look at.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('is_publisher')
            || $user->venues()->exists()
            || $user->promoters()->exists();
    }

    public function view(User $user, Widget $widget): bool
    {
        return $this->runsOwner($user, $widget);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Whether a widget can be made for this venue or promoter, or of this
     * account's bookmarks: only a publisher's own.
     */
    public function createFor(User $user, Model $owner): bool
    {
        if ($owner instanceof User) {
            return $owner->is($user) && $user->hasRole('is_publisher');
        }

        return Gate::forUser($user)->allows('update', $owner);
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
        if ($widget->isForBookmarks()) {
            return (int) $widget->owner_id === (int) $user->getKey();
        }

        return $widget->owner !== null
            && Gate::forUser($user)->allows('update', $widget->owner);
    }
}
