<?php

namespace App\Services\Shop\Payments;

/**
 * A Stripe Checkout session just made: its id, and the page to send the
 * customer to.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
