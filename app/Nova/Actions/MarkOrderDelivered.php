<?php

namespace App\Nova\Actions;

use App\Models\Order;
use App\Services\Shop\OrderFulfilment;
use App\Support\Shop\OrderStatus;
use Laravel\Nova\Fields\ActionFields;

/**
 * Ticks off posted orders as arrived.
 */
class MarkOrderDelivered extends OrderStep
{
    public $name = 'Mark as delivered';

    public $confirmButtonText = 'Mark as delivered';

    protected function pastTense(): string
    {
        return 'marked delivered';
    }

    public function applies(Order $order): bool
    {
        return $order->status === OrderStatus::Posted;
    }

    protected function apply(Order $order, ActionFields $fields): void
    {
        app(OrderFulfilment::class)->deliver($order);
    }
}
