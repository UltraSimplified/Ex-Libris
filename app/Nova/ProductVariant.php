<?php

namespace App\Nova;

use App\Models\ProductVariant as ProductVariantModel;
use Illuminate\Validation\Validator;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Outl1ne\NovaColorField\Color;

/**
 * One colour and size of a product, with its price and stock. Reached from
 * its product, never from the menu.
 *
 * @extends resource<ProductVariantModel>
 */
class ProductVariant extends Resource
{
    /**
     * @var class-string<ProductVariantModel>
     */
    public static $model = ProductVariantModel::class;

    /**
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * @var array
     */
    public static $search = [
        'colour',
        'size',
        'sku',
    ];

    /**
     * @var array<string, string>
     */
    public static $sort = [
        'position' => 'asc',
    ];

    public static function label(): string
    {
        return 'Colours and sizes';
    }

    public static function singularLabel(): string
    {
        return 'Colour and size';
    }

    public function title(): string
    {
        return $this->resource->displayName();
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make('Product', 'product', Product::class)->hideFromIndex(),
            Text::make('Colour')->nullable()->sortable()->rules('nullable', 'max:60'),
            Color::make('Swatch', 'colour_hex')->nullable()->hideFromIndex()
                ->help('The colour of its swatch on the product page.'),
            Text::make('Size')->nullable()->sortable()->rules('nullable', 'max:10')
                ->help('XS, S, M, L, XL, XXL or 3XL sort in that order.'),
            Currency::make('Price')->currency('GBP')->asMinorUnits()->sortable()->rules('required', 'numeric', 'min:0'),
            Currency::make('Sale price', 'sale_price')->currency('GBP')->asMinorUnits()->nullable()->sortable()
                ->rules('nullable', 'numeric', 'min:0')
                ->help('To put it on sale: the lower price it sells for, with the price shown struck through beside it. Leave empty when it is not on sale.'),
            Number::make('Stock')->nullable()->min(0)->step(1)->sortable()->rules('nullable', 'integer', 'min:0')
                ->displayUsing(fn (?int $stock): string => $stock === null ? 'No limit' : (string) $stock)
                ->help('How many are left. Leave empty if there is no limit.'),
            Text::make('SKU', 'sku')->nullable()->hideFromIndex()
                ->rules('nullable', 'max:60')
                ->creationRules('unique:product_variants,sku')
                ->updateRules('unique:product_variants,sku,{{resourceId}}'),
            Number::make('Position')->min(0)->step(1)->default(0)->rules('required', 'integer', 'min:0')->hideFromIndex(),
        ];
    }

    /**
     * A colour and size may appear only once on a product. The database
     * holds that for shirts, but cannot for two variants with neither a
     * colour nor a size, so it is checked here as well.
     */
    protected static function afterValidation(NovaRequest $request, $validator): void
    {
        /** @var Validator $validator */
        $productId = $request->input('product') ?? $request->input('viaResourceId');
        $colour = $request->filled('colour') ? $request->input('colour') : null;
        $size = $request->filled('size') ? $request->input('size') : null;

        if ($productId === null) {
            return;
        }

        $taken = ProductVariantModel::query()
            ->where('product_id', $productId)
            ->when($colour === null, fn ($query) => $query->whereNull('colour'), fn ($query) => $query->where('colour', $colour))
            ->when($size === null, fn ($query) => $query->whereNull('size'), fn ($query) => $query->where('size', $size))
            ->when($request->resourceId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        if ($taken) {
            $validator->errors()->add('size', 'This product already has that colour and size.');
        }
    }
}
