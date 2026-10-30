<?php

namespace App\Nova\Actions;

use App\Models\Order;
use App\Services\Shop\OrderRefunds;
use App\Support\Shop\OrderStatus;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Gives money back on an order that has already left - all of what is
 * left to refund, or part of it - through Stripe, and emails the customer.
 */
class RefundOrder extends OrderStep
{
    public $name = 'Refund';

    public $confirmText = 'Refund this order? The money goes back to the customer through Stripe straight away, and this cannot be undone.';

    public $confirmButtonText = 'Refund';

    public function __construct()
    {
        parent::__construct();

        $this->sole();
    }

    protected function pastTense(): string
    {
        return 'refunded';
    }

    public function applies(Order $order): bool
    {
        return $order->status->hasLeft()
            && $order->status !== OrderStatus::Refunded
            && $order->refundable() > 0;
    }

    protected function apply(Order $order, ActionFields $fields): void
    {
        $amount = $fields->get('amount');

        app(OrderRefunds::class)->refund(
            $order,
            is_numeric($amount) ? (int) round((float) $amount * 100) : null,
            auth()->user(),
        );
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Currency::make('Amount')
                ->currency('GBP')
                ->nullable()
                ->rules('nullable', 'numeric', 'min:0.01')
                ->help('Leave empty to refund everything still unrefunded. A part refund leaves the order as it is.'),
        ];
    }
}
