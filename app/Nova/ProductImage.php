<?php

namespace App\Nova;

use App\Models\ProductImage as ProductImageModel;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * One of a product's pictures after the main one. Reached from its
 * product, never from the menu.
 */
class ProductImage extends Resource
{
    /**
     * @var class-string<ProductImageModel>
     */
    public static $model = ProductImageModel::class;

    /**
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * @var string
     */
    public static $title = 'alt_text';

    /**
     * @var array
     */
    public static $search = [];

    /**
     * @var array<string, string>
     */
    public static $sort = [
        'position' => 'asc',
    ];

    public static function label(): string
    {
        return 'Pictures';
    }

    public static function singularLabel(): string
    {
        return 'Picture';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make('Product', 'product', Product::class)->hideFromIndex(),
            ImageCropper::make('Image', 'path')->resize(1600)->convert('webp')->disk('do')->indexWidth(120)->prunable()->rules('required'),
            Text::make('Colour')->nullable()->rules('nullable', 'max:60')
                ->help('Spelt as on the colour and size it shows, to show only when that colour is chosen. Leave empty to show for every colour.'),
            Text::make('Alt text', 'alt_text')->nullable()->hideFromIndex()->rules('nullable', 'max:255')
                ->help('What the picture shows, for people who cannot see it.'),
            Number::make('Position')->min(0)->step(1)->default(0)->rules('required', 'integer', 'min:0'),
        ];
    }
}
