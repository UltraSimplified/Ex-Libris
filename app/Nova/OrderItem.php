<?php

namespace App\Nova;

use App\Models\OrderItem as OrderItemModel;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * One line of an order, as it was bought. Never edited.
 *
 * @extends resource<OrderItemModel>
 */
class OrderItem extends Resource
{
    /**
     * @var class-string<OrderItemModel>
     */
    public static $model = OrderItemModel::class;

    /**
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * @var string
     */
    public static $title = 'product_name';

    /**
     * @var array
     */
    public static $search = [];

    public static function authorizedToCreate(Request $request): false
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): false
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
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Product', 'product_name'),
            Text::make('Colour and size', 'variant_name'),
            Number::make('Quantity'),
            Currency::make('Price', 'unit_price')->currency('GBP')->asMinorUnits(),
            Text::make('Packing', 'postage_band'),
        ];
    }
}
