<?php

namespace App\Mail\Shop;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShopSettings;
use App\Services\Images\ProductThumbnail;
use App\Services\Images\StaticMap;
use App\Services\Shop\OrderMaps;
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
 *
 * The pictures and the map are made here rather than in the webhook that
 * takes the payment, because this is queued and that is not.
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
        $maps = app(OrderMaps::class);
        $collecting = $this->order->fulfilment === Fulfilment::Collection;

        $this->order->loadMissing('items.variant.product');

        return new Content(
            markdown: 'mail.shop.order-confirmed',
            with: [
                'order' => $this->order,
                'collecting' => $collecting,
                'collection' => [
                    'name' => $settings->collection_name,
                    'address' => $settings->collectionAddressLines(),
                ],
                'lines' => $this->lines(),
                'map' => $collecting
                    ? $maps->collectionPicture()
                    : $maps->deliveryPicture($this->order),
                'mapSize' => $maps->pictureSize(),
                'mapCredit' => StaticMap::CREDIT,
                'orderUrl' => $this->order->guestUrl(),
                'claimUrl' => $this->order->user_id === null ? $this->order->claimUrl() : null,
            ],
        );
    }

    /**
     * What they bought, each line with a picture of it.
     *
     * The picture is a JPEG copy rather than the webp the shop pages use,
     * since Outlook will not render webp.
     *
     * @return list<array{name: string, quantity: int, subtotal: int, picture: ?string}>
     */
    private function lines(): array
    {
        $thumbnails = app(ProductThumbnail::class);

        return $this->order->items->map(fn (OrderItem $item): array => [
            'name' => $item->product_name.($item->variant_name === ProductVariant::STANDARD
                ? ''
                : ' ('.$item->variant_name.')'),
            'quantity' => $item->quantity,
            'subtotal' => $item->subtotal(),
            'picture' => $thumbnails->url($item->picture()),
        ])->all();
    }
}
