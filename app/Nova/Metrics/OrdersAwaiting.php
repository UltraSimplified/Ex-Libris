<?php

namespace App\Nova\Metrics;

use App\Models\Order;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\OrderStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/**
 * How many paid orders are waiting for the office: to post, or to hand over
 * at Good on Paper.
 */
class OrdersAwaiting extends Value
{
    public function __construct(private Fulfilment $fulfilment)
    {
        parent::__construct();

        $this->name = $fulfilment === Fulfilment::Post ? 'Orders to post' : 'Orders to collect';
    }

    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->result(
            Order::query()
                ->where('fulfilment', $this->fulfilment->value)
                ->whereIn('status', OrderStatus::awaitingValues($this->fulfilment))
                ->count()
        )->allowZeroResult();
    }

    public function uriKey(): string
    {
        return 'orders-awaiting-'.$this->fulfilment->value;
    }
}
