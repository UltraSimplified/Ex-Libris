<?php

namespace App\Mail\Shop;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;
use Laravel\Nova\Nova;

/**
 * Tells the orders inbox that orders were paid for without Stripe's webhook
 * saying so. The orders have been put right; the webhook still needs fixing.
 */
class WebhookLooksBroken extends Mailable
{
    /**
     * @param  Collection<int, Order>  $orders
     */
    public function __construct(public Collection $orders) {}

    public function envelope(): Envelope
    {
        $count = $this->orders->count();

        return new Envelope(
            subject: "Stripe's webhook looks broken: {$count} paid ".Str::plural('order', $count).' missed',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.webhook-looks-broken',
            with: [
                'orders' => $this->orders,
                'webhookUrl' => route('cashier.webhook'),
                'officeUrl' => fn (Order $order): string => url(Nova::url('/resources/orders/'.$order->id)),
            ],
        );
    }
}
