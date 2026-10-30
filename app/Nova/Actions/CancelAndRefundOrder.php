<?php

namespace App\Nova\Actions;

use App\Models\Order;
use App\Services\Shop\OrderRefunds;
use App\Support\Shop\OrderStatus;
use Laravel\Nova\Fields\ActionFields;

/**
 * Stops orders that have not left yet and refunds them in full through
 * Stripe. Their items go back on the shelf and each customer is emailed.
 */
class CancelAndRefundOrder extends OrderStep
{
    public $name = 'Cancel and refund';

    public $confirmText = 'Cancel the selected orders and refund them in full? The money goes back to the customer through Stripe straight away, and this cannot be undone.';

    public $confirmButtonText = 'Cancel and refund';

    protected function pastTense(): string
    {
        return 'cancelled and refunded';
    }

    public function applies(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Paid, OrderStatus::Ready], true);
    }

    protected function apply(Order $order, ActionFields $fields): void
    {
        app(OrderRefunds::class)->cancel($order, auth()->user());
    }
}
