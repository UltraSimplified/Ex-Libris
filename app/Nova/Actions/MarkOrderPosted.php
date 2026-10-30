<?php

namespace App\Nova\Actions;

use App\Models\Order;
use App\Services\Shop\OrderFulfilment;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\OrderStatus;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Ticks off paid orders as posted, and emails each customer that theirs is
 * on its way.
 */
class MarkOrderPosted extends OrderStep
{
    public $name = 'Mark as posted';

    public $confirmButtonText = 'Mark as posted';

    protected function pastTense(): string
    {
        return 'posted';
    }

    public function applies(Order $order): bool
    {
        return $order->fulfilment === Fulfilment::Post && $order->status === OrderStatus::Paid;
    }

    protected function apply(Order $order, ActionFields $fields): void
    {
        app(OrderFulfilment::class)->post($order, $fields->get('tracking_number'));
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Tracking number', 'tracking_number')
                ->nullable()
                ->rules('nullable', 'max:255')
                ->help('Optional. Goes in the customer\'s email. When marking several at once, leave it empty.'),
        ];
    }
}
