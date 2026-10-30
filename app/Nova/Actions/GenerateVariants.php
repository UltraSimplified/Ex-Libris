<?php

namespace App\Nova\Actions;

use App\Models\Product;
use App\Services\Shop\VariantGrid;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Gives a product every colour in every size at once - a t-shirt in five
 * colours and six sizes is thirty variants, too many to add by hand.
 */
class GenerateVariants extends Action
{
    public $name = 'Generate colours and sizes';

    public $confirmButtonText = 'Generate';

    /**
     * Nova builds actions with `new static(...)` rather than through the
     * container, so the grid is resolved here rather than injected.
     *
     * @param  Collection<int, Product>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $colours = VariantGrid::parseColours((string) $fields->get('colours'));
        $sizes = VariantGrid::parseSizes((string) $fields->get('sizes'));

        if ($colours === [] && $sizes === []) {
            return ActionResponse::danger('Give at least one colour or size.');
        }

        $price = (int) round((float) $fields->get('price') * 100);
        $stock = is_numeric($fields->get('stock')) ? (int) $fields->get('stock') : null;
        $salePrice = is_numeric($fields->get('sale_price')) ? (int) round((float) $fields->get('sale_price') * 100) : null;

        $made = 0;
        $kept = 0;
        $removedStandard = false;

        foreach ($models as $product) {
            $result = app(VariantGrid::class)->generate($product, $colours, $sizes, $price, $stock, $salePrice);
            $made += $result['made'];
            $kept += $result['kept'];
            $removedStandard = $removedStandard || $result['removedStandard'];
        }

        $message = 'Made '.$made.' '.Str::plural('colour and size', $made).'.';

        if ($kept > 0) {
            $message .= ' Left '.$kept.' that '.($kept === 1 ? 'was' : 'were').' already there as '.($kept === 1 ? 'it was' : 'they were').'.';
        }

        if ($removedStandard) {
            $message .= ' Its single price and stock are gone: set them on each colour and size now.';
        }

        return ActionResponse::message($message);
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Colours')
                ->help('Comma separated, each with an optional swatch colour: Black #111111, White #ffffff, Natural. Leave empty if it comes in one colour.'),
            Text::make('Sizes')
                ->help('Comma separated: S, M, L, XL. Leave empty if it comes in one size.'),
            Currency::make('Price')
                ->currency('GBP')
                ->rules('required', 'numeric', 'min:0')
                ->help('For every one made. Change any afterwards on its own row.'),
            Currency::make('Sale price', 'sale_price')
                ->currency('GBP')
                ->nullable()
                ->rules('nullable', 'numeric', 'min:0')
                ->help('Only if they are on sale: the lower price they sell for, with the price struck through.'),
            Number::make('Stock')
                ->min(0)
                ->step(1)
                ->nullable()
                ->rules('nullable', 'integer', 'min:0')
                ->help('For every one made. Leave empty if there is no limit.'),
        ];
    }
}
