<?php

namespace App\Listeners;

use App\Services\Shop\OrderPayments;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Stripe's word on the shop's payments, as Cashier passes it on.
 *
 * Runs as the webhook arrives rather than from the queue, so an order is
 * paid by the time Stripe hears back. Anything that is not about one of
 * our orders is passed over.
 */
class HandleShopWebhook
{
    public function __construct(private OrderPayments $payments) {}

    public function handle(WebhookReceived $event): void
    {
        $object = (array) ($event->payload['data']['object'] ?? []);

        match ($event->payload['type'] ?? null) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->payments->completed($object),
            'checkout.session.expired', 'checkout.session.async_payment_failed' => $this->payments->expired($object),
            'charge.refunded' => $this->payments->refunded($object),
            default => null,
        };
    }
}
