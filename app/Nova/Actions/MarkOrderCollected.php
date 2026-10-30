<?php

namespace App\Nova\Actions;

use App\Models\Order;
use App\Services\Shop\OrderFulfilment;
use App\Support\Shop\OrderStatus;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Ticks off collection orders as handed over.
 */
class MarkOrderCollected extends OrderStep
{
    public $name = 'Mark as collected';

    public $confirmButtonText = 'Mark as collected';

    protected function pastTense(): string
    {
        return 'marked collected';
    }

    public function applies(Order $order): bool
    {
        return $order->status === OrderStatus::Ready;
    }

    protected function apply(Order $order, ActionFields $fields): void
    {
        app(OrderFulfilment::class)->collect($order, $fields->get('collected_by'));
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Collected by', 'collected_by')
                ->nullable()
                ->rules('nullable', 'max:255')
                ->help('Only if someone other than the customer picked it up.'),
        ];
    }
}
