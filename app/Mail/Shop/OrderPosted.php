<?php

namespace App\Mail\Shop;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a customer their order is in the post.
 */
class OrderPosted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address((string) config('shop.notify.to'), 'Good on Paper orders')],
            subject: "Your Good on Paper order {$this->order->reference} is on its way",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.order-posted',
            with: [
                'order' => $this->order->loadMissing('items'),
                'orderUrl' => $this->order->guestUrl(),
            ],
        );
    }
}
