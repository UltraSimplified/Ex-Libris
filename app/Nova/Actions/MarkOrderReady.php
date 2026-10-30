<?php

namespace App\Nova\Actions;

use App\Models\Order;
use App\Services\Shop\OrderFulfilment;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\OrderStatus;
use Laravel\Nova\Fields\ActionFields;

/**
 * Marks paid collection orders as packed and waiting, and emails each
 * customer where and when to collect.
 */
class MarkOrderReady extends OrderStep
{
    public $name = 'Ready for collection';

    public $confirmButtonText = 'Mark as ready';

    protected function pastTense(): string
    {
        return 'marked ready';
    }

    public function applies(Order $order): bool
    {
        return $order->fulfilment === Fulfilment::Collection && $order->status === OrderStatus::Paid;
    }

    protected function apply(Order $order, ActionFields $fields): void
    {
        app(OrderFulfilment::class)->ready($order);
    }
}
