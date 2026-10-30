<?php

namespace App\Mail\Shop;

use App\Models\Order;
use App\Models\ShopSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a customer their order is waiting for them at Good on Paper, where
 * and when to collect it, and what to say when they do.
 */
class OrderReadyForCollection extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address((string) config('shop.notify.to'), 'Good on Paper orders')],
            subject: "Your Good on Paper order {$this->order->reference} is ready to collect",
        );
    }

    public function content(): Content
    {
        $settings = ShopSettings::current();

        return new Content(
            markdown: 'mail.shop.order-ready-for-collection',
            with: [
                'order' => $this->order->loadMissing('items'),
                'collection' => [
                    'name' => $settings->collection_name,
                    'address' => $settings->collectionAddressLines(),
                    'hours' => $settings->collection_hours,
                ],
            ],
        );
    }
}
