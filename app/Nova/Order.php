<?php

namespace App\Nova;

use App\Models\Order as OrderModel;
use App\Nova\Actions\CancelAndRefundOrder;
use App\Nova\Actions\MarkOrderCollected;
use App\Nova\Actions\MarkOrderDelivered;
use App\Nova\Actions\MarkOrderPosted;
use App\Nova\Actions\MarkOrderReady;
use App\Nova\Actions\PrintPackingSlips;
use App\Nova\Actions\RefundOrder;
use App\Nova\Filters\OrderStatusFilter;
use App\Nova\Lenses\OrdersForCollection;
use App\Nova\Lenses\OrdersToPost;
use App\Nova\Metrics\OrdersAwaiting;
use App\Nova\Metrics\StalePendingOrders;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\OrderStatus;
use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;

/**
 * Orders from the shop. Made by checkout and moved on by Stripe and the
 * office's actions, never typed in: only the notes and the tracking number
 * are edited here.
 *
 * @extends resource<OrderModel>
 */
class Order extends Resource
{
    /**
     * @var class-string<OrderModel>
     */
    public static $model = OrderModel::class;

    /**
     * @var string
     */
    public static $title = 'reference';

    /**
     * @var array
     */
    public static $search = [
        'reference',
        'email',
        'name',
    ];

    /**
     * @var array<string, string>
     */
    public static $sort = [
        'id' => 'desc',
    ];

    public static function authorizedToCreate(Request $request): false
    {
        return false;
    }

    public function authorizedToDelete(Request $request): false
    {
        return false;
    }

    public function authorizedToReplicate(Request $request): false
    {
        return false;
    }

    /**
     * @return array<int, Field|Panel>
     */
    public function fields(NovaRequest $request): array
    {
        $money = fn (string $name, string $attribute): Currency => Currency::make($name, $attribute)
            ->currency('GBP')
            ->asMinorUnits()
            ->exceptOnForms();

        return [
            Text::make('Reference')->sortable()->exceptOnForms(),
            Badge::make('Status', fn (): string => $this->resource->status->label())
                ->map([
                    OrderStatus::Pending->label() => 'warning',
                    OrderStatus::Paid->label() => 'info',
                    OrderStatus::Posted->label() => 'success',
                    OrderStatus::Delivered->label() => 'success',
                    OrderStatus::Ready->label() => 'info',
                    OrderStatus::Collected->label() => 'success',
                    OrderStatus::Cancelled->label() => 'danger',
                    OrderStatus::Refunded->label() => 'danger',
                ]),
            Text::make('Delivery', fn (): string => $this->resource->fulfilment === Fulfilment::Collection ? 'Collect' : 'Post')
                ->exceptOnForms(),
            Text::make('Customer', 'name')->exceptOnForms()->sortable(),
            Text::make('Email')->exceptOnForms()->hideFromIndex(),
            Text::make('Phone')->exceptOnForms()->hideFromIndex(),
            BelongsTo::make('Account', 'user', User::class)->nullable()->exceptOnForms()->hideFromIndex(),
            $money('Total', 'total')->sortable(),
            Boolean::make('Oversold')->exceptOnForms()->hideFromIndex(),
            DateTime::make('Paid', 'paid_at')->exceptOnForms()->sortable(),

            new Panel('Money', [
                $money('Subtotal', 'subtotal')->onlyOnDetail(),
                $money('Postage', 'shipping')->onlyOnDetail(),
                Boolean::make('Postage capped', 'postage_capped')->onlyOnDetail(),
                $money('Refunded', 'refunded_amount')->onlyOnDetail(),
                Text::make('Receipt', fn (): ?string => $this->resource->receipt_url
                    ? '<a class="link-default" target="_blank" href="'.e($this->resource->receipt_url).'">Stripe receipt</a>'
                    : null)->asHtml()->onlyOnDetail(),
                Text::make('Payment', fn (): ?string => $this->resource->stripe_payment_intent_id
                    ? '<a class="link-default" target="_blank" href="https://dashboard.stripe.com/payments/'.e($this->resource->stripe_payment_intent_id).'">Open in Stripe</a>'
                    : null)->asHtml()->onlyOnDetail(),
            ]),

            new Panel('Addresses', [
                Text::make('Deliver to', fn (): string => $this->addressHtml($this->resource->shipping_address, 'Collecting'))
                    ->asHtml()->onlyOnDetail(),
                Text::make('Billing address', fn (): string => $this->addressHtml($this->resource->billing_address, '-'))
                    ->asHtml()->onlyOnDetail(),
            ]),

            new Panel('Fulfilment', [
                Text::make('Tracking number', 'tracking_number')->nullable()->hideFromIndex()->rules('nullable', 'max:255'),
                Textarea::make('Notes')->nullable()->alwaysShow()->help('For the office only.'),
                DateTime::make('Posted', 'posted_at')->onlyOnDetail(),
                DateTime::make('Delivered', 'delivered_at')->onlyOnDetail(),
                DateTime::make('Ready to collect', 'ready_at')->onlyOnDetail(),
                DateTime::make('Collected', 'collected_at')->onlyOnDetail(),
                DateTime::make('Cancelled', 'cancelled_at')->onlyOnDetail(),
                DateTime::make('Refunded', 'refunded_at')->onlyOnDetail(),
            ]),

            HasMany::make('Items', 'items', OrderItem::class),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            PrintPackingSlips::make(),
            MarkOrderPosted::make(),
            MarkOrderDelivered::make(),
            MarkOrderReady::make(),
            MarkOrderCollected::make(),
            CancelAndRefundOrder::make(),
            RefundOrder::make(),
        ];
    }

    /**
     * @return array<int, Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [
            OrdersToPost::make(),
            OrdersForCollection::make(),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            OrderStatusFilter::make(),
        ];
    }

    /**
     * @return array<int, Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [
            new OrdersAwaiting(Fulfilment::Post),
            new OrdersAwaiting(Fulfilment::Collection),
            StalePendingOrders::make(),
        ];
    }

    /**
     * @param  array<string, ?string>|null  $address
     */
    private function addressHtml(?array $address, string $none): string
    {
        $lines = OrderModel::addressLines($address);

        return $lines === [] ? e($none) : implode('<br>', array_map('e', $lines));
    }
}
