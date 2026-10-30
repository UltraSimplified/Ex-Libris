<?php

namespace App\Listeners;

use App\Events\Shop\OrderPaid;
use App\Mail\Shop\NewOrderForOffice;
use App\Mail\Shop\OrderConfirmed;
use App\Models\User;
use App\Support\Shop\Fulfilment;
use Illuminate\Support\Facades\Mail;
use Laravel\Nova\Notifications\NovaNotification;
use Laravel\Nova\URL;

/**
 * Tells everyone who needs to know that an order is paid: the customer,
 * the orders inbox, and the office in the Office's notifications.
 */
class NotifyOrderPaid
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order->loadMissing('items');

        if (filled($order->email)) {
            Mail::to($order->email, $order->name)->queue(new OrderConfirmed($order));
        }

        Mail::to(config('shop.notify.to'))
            ->cc(array_filter([config('shop.notify.cc')]))
            ->queue(new NewOrderForOffice($order));

        $total = '£'.number_format($order->total / 100, 2);
        $how = $order->fulfilment === Fulfilment::Collection ? 'to collect' : 'to post';

        User::query()
            ->whereIn('email', User::OFFICE_EMAILS)
            ->get()
            ->each(fn (User $staff) => $staff->notify(
                NovaNotification::make()
                    ->message("New order {$order->reference}: {$total}, {$how}.")
                    ->action('View order', URL::make('/resources/orders/'.$order->id))
                    ->icon('shopping-bag')
                    ->type('success'),
            ));
    }
}
