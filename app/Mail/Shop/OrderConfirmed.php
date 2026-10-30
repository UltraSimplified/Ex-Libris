<?php

namespace App\Mail\Shop;

use App\Models\Order;
use App\Models\ShopSettings;
use App\Support\Shop\Fulfilment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a customer their order is paid for: what they bought, what it came
 * to, and how it will reach them.
 */
class OrderConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address((string) config('shop.notify.to'), 'Good on Paper orders')],
            subject: "Your Good on Paper order {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        $settings = ShopSettings::current();

        return new Content(
            markdown: 'mail.shop.order-confirmed',
            with: [
                'order' => $this->order->loadMissing('items'),
                'collecting' => $this->order->fulfilment === Fulfilment::Collection,
                'collection' => [
                    'name' => $settings->collection_name,
                    'address' => $settings->collectionAddressLines(),
                ],
                'orderUrl' => $this->order->guestUrl(),
                'claimUrl' => $this->order->user_id === null ? $this->order->claimUrl() : null,
            ],
        );
    }
}
