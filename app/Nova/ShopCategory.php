<?php

namespace App\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * A shelf in the shop. Hidden from the public until published.
 *
 * @extends \App\Nova\Resource<\App\Models\ShopCategory>
 */
class ShopCategory extends Resource
{
    /**
     * @var class-string<\App\Models\ShopCategory>
     */
    public static $model = \App\Models\ShopCategory::class;

    /**
     * @var string
     */
    public static $title = 'name';

    /**
     * @var array
     */
    public static $search = [
        'name',
    ];

    /**
     * @var array<string, string>
     */
    public static $sort = [
        'position' => 'asc',
    ];

    /**
     * Opens to its page rather than the form, as that is where its products
     * are listed and dragged into order. The form is the pencil at the top.
     *
     * @var string
     */
    public static $clickAction = 'view';

    public static function label(): string
    {
        return 'Categories';
    }

    public static function singularLabel(): string
    {
        return 'Category';
    }

    public static function uriKey(): string
    {
        return 'shop-categories';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withCount('products');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Name')->sortable()->rules('required', 'max:255'),
            Slug::make('Slug')
                ->from('Name')
                ->rules('required', 'max:255', 'alpha_dash')
                ->creationRules('unique:shop_categories,slug')
                ->updateRules('unique:shop_categories,slug,{{resourceId}}')
                ->hideFromIndex()
                ->help('The category\'s address: /shop/this-slug.'),
            ImageCropper::make('Image')->resize(1600)->convert('webp')->disk('do')->indexWidth(150)->prunable()->nullable(),
            Textarea::make('Description')->nullable()->rows(3),
            Number::make('Products', 'products_count')->onlyOnIndex()->sortable(),
            Number::make('Position')->min(0)->step(1)->default(0)->rules('required', 'integer', 'min:0')->sortable(),
            Boolean::make('Published', 'is_published')->sortable(),
            HasMany::make('Products', 'products', Product::class),
        ];
    }
}
