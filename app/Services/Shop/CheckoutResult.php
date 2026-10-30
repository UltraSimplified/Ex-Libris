<?php

namespace App\Services\Shop;

use App\Models\Order;
use App\Services\Shop\Payments\CheckoutSession;

/**
 * The order a checkout made, and the Stripe session to pay for it in.
 */
final readonly class CheckoutResult
{
    public function __construct(
        public Order $order,
        public CheckoutSession $session,
    ) {}
}
