<?php

namespace App\Mail\Shop;

use App\Models\Order;
use App\Support\Shop\Fulfilment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Laravel\Nova\Nova;

/**
 * Tells the orders inbox that an order has been paid for and needs posting
 * or packing for collection, or, for a donation, nothing at all.
 */
class NewOrderForOffice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New order {$this->order->reference} {$this->order->fulfilment->officeTask()}".($this->order->oversold ? ' - OVERSOLD' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.new-order-for-office',
            with: [
                'order' => $this->order->loadMissing('items'),
                'posting' => $this->order->fulfilment === Fulfilment::Post,
                'officeUrl' => url(Nova::url('/resources/orders/'.$this->order->id)),
            ],
        );
    }
}
