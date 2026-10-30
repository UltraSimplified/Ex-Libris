<?php

namespace App\Nova\Metrics;

use App\Models\Order;
use App\Support\Shop\OrderStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/**
 * Orders sent to Stripe more than an hour ago that are still waiting for
 * payment. Stripe cancels an unpaid one after half an hour, so any here
 * mean its webhook is not getting through - and customers may have paid
 * for orders the shop does not know about.
 */
class StalePendingOrders extends Value
{
    public $name = 'Orders stuck awaiting payment';

    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->result(
            Order::query()
                ->where('status', OrderStatus::Pending->value)
                ->where('created_at', '<', now()->subHour())
                ->count()
        )->allowZeroResult();
    }

    public function uriKey(): string
    {
        return 'stale-pending-orders';
    }
}
