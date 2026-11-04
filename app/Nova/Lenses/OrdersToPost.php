<?php

namespace App\Nova\Lenses;

use App\Nova\Actions\CancelAndRefundOrder;
use App\Nova\Actions\MarkOrderPosted;
use App\Nova\Actions\PrintPackingSlips;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\OrderStatus;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\Paginator;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;

/**
 * Paid orders waiting to go in the post, oldest first: whoever paid first
 * has been waiting longest. Print the slips, pack, then mark them posted.
 */
class OrdersToPost extends Lens
{
    public $name = 'To post';

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
            $query->where('fulfilment', Fulfilment::Post->value)
                ->whereIn('status', OrderStatus::awaitingValues(Fulfilment::Post))
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
            Text::make('Town', fn (): ?string => $this->shipping_address['town'] ?? null),
            Number::make('Items', 'item_count'),
            Boolean::make('Oversold'),
            DateTime::make('Paid', 'paid_at')->sortable(),
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
            CancelAndRefundOrder::make(),
        ];
    }

    public function uriKey(): string
    {
        return 'to-post';
    }
}
