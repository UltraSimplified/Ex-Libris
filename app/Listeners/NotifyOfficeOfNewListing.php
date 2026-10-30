<?php

namespace App\Listeners;

use App\Events\Dashboard\ListingCreated;
use App\Models\User;
use Laravel\Nova\Notifications\NovaNotification;
use Laravel\Nova\URL;

/**
 * Tells the office, in the Office's notifications, of every listing added
 * from a dashboard. Whoever added it is not told of their own.
 */
class NotifyOfficeOfNewListing
{
    public function handle(ListingCreated $event): void
    {
        $listing = $event->listing->loadMissing('user');
        $author = $listing->user;

        $message = "New event created by {$author?->name}: {$listing->title}";

        User::query()
            ->whereIn('email', User::OFFICE_EMAILS)
            ->when($author, fn ($staff) => $staff->whereKeyNot($author->id))
            ->get()
            ->each(fn (User $staff) => $staff->notify(
                NovaNotification::make()
                    ->message($message)
                    ->action('View listing', URL::make('/resources/listings/'.$listing->id))
                    ->icon('calendar')
                    ->type('info'),
            ));
    }
}
