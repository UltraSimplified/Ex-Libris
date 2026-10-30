<?php

namespace App\Mail\Shop;

use App\Models\Order;
use App\Support\Shop\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a customer money has gone back to them, and whether their order
 * was cancelled.
 *
 * @param  int  $amount  what was refunded this time, in pence
 */
class OrderRefunded extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public int $amount) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address((string) config('shop.notify.to'), 'Good on Paper orders')],
            subject: "Refund for your Good on Paper order {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.order-refunded',
            with: [
                'order' => $this->order,
                // Not 'amount': the public property of that name would be
                // handed to the view after this and win, in pence.
                'refunded' => '£'.number_format($this->amount / 100, 2),
                'cancelled' => $this->order->status === OrderStatus::Cancelled,
            ],
        );
    }
}
