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
 * or packing for collection.
 */
class NewOrderForOffice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $how = $this->order->fulfilment === Fulfilment::Collection ? 'to collect' : 'to post';

        return new Envelope(
            subject: "New order {$this->order->reference} {$how}".($this->order->oversold ? ' - OVERSOLD' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shop.new-order-for-office',
            with: [
                'order' => $this->order->loadMissing('items'),
                'collecting' => $this->order->fulfilment === Fulfilment::Collection,
                'officeUrl' => url(Nova::url('/resources/orders/'.$this->order->id)),
            ],
        );
    }
}
