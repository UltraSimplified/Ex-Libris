<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Laravel\Nova\Notifications\NovaNotification;
use Laravel\Nova\URL;

/**
 * Tells the office, in the Office's notifications, of everybody who signs
 * up on the site, with a link straight to the new account's edit form so a
 * role can be given or a spam account dealt with.
 */
class NotifyOfficeOfRegistration
{
    public function handle(Registered $event): void
    {
        $account = $event->user;

        if (! $account instanceof User) {
            return;
        }

        User::query()
            ->whereIn('email', User::OFFICE_EMAILS)
            ->get()
            ->each(fn (User $staff) => $staff->notify(
                NovaNotification::make()
                    ->message("New registration: {$account->name} ({$account->email})")
                    ->action('Edit user', URL::make("/resources/users/{$account->id}/edit"))
                    ->icon('user-plus')
                    ->type('info'),
            ));
    }
}
