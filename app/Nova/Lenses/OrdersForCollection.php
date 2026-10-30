<?php

namespace App\Nova\Lenses;

use App\Nova\Actions\CancelAndRefundOrder;
use App\Nova\Actions\MarkOrderCollected;
use App\Nova\Actions\MarkOrderReady;
use App\Nova\Actions\PrintPackingSlips;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\OrderStatus;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\Paginator;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;

/**
 * Collection orders still to hand over: those paid and waiting to be
 * packed, and those packed and waiting on the shelf, oldest first.
 */
class OrdersForCollection extends Lens
{
    public $name = 'For collection';

    /**
     * @var array<int, string>
     */
    public static $search = [
        'reference',
        'name',
    ];

    public static function query(LensRequest $request, Builder $query): Builder|Paginator
    {
        return $request->withOrdering($request->withFilters(
            $query->where('fulfilment', Fulfilment::Collection->value)
                ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Ready->value])
                ->withSum('items as item_count', 'quantity')
                ->orderBy('paid_at')
        ));
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Reference'),
            Text::make('Customer', 'name'),
            Text::make('Phone'),
            Number::make('Items', 'item_count'),
            Badge::make('Status', fn (): string => $this->status->label())
                ->map([OrderStatus::Paid->label() => 'warning', OrderStatus::Ready->label() => 'info']),
            DateTime::make('Paid', 'paid_at')->sortable(),
            DateTime::make('Ready since', 'ready_at'),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            PrintPackingSlips::make(),
            MarkOrderReady::make(),
            MarkOrderCollected::make(),
            CancelAndRefundOrder::make(),
        ];
    }

    public function uriKey(): string
    {
        return 'for-collection';
    }
}
