<?php

namespace App\Support\Shop;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * An order as the customer is shown it, on the thank-you page and the
 * page their emails link to. Money is in pence.
 */
class OrderSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'reference' => $order->reference,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'fulfilment' => $order->fulfilment->value,
            'email' => $order->email,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'product' => $item->product_name,
                'variant' => $item->variant_name,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal(),
            ])->all(),
            'subtotal' => $order->subtotal,
            'shipping' => $order->shipping,
            'total' => $order->total,
            'shipping_address' => Order::addressLines($order->shipping_address),
            'tracking_number' => $order->tracking_number,
            'paid_at' => $order->paid_at?->toIso8601String(),
        ];
    }
}
