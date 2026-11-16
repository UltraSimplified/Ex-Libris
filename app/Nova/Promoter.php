<?php

namespace App\Nova;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\MorphMany;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Outl1ne\NovaColorField\Color;
use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * @extends \App\Nova\Resource<\App\Models\Promoter>
 */
class Promoter extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Promoter>
     */
    public static $model = \App\Models\Promoter::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'name',
    ];

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $sort = [
        'name',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field>
     *
     * @throws \Exception
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Name')->sortable()->rules('required', 'max:255'),
            BelongsTo::make('Owner', 'user', User::class)
                ->nullable()
                ->searchable()
                ->help('The account that can edit this promoter, and its listings, from the front-end dashboard.'),
            Text::make('website')->nullable()->hideFromIndex(),
            Text::make('email')->nullable()->hideFromIndex(),
            Text::make('phone')->hideFromIndex(),
            ImageCropper::make('Logo')
                ->croppable(512 / 512)
                ->resize(512, 512)
                ->convert('webp')
                ->disk('do')
                ->nullable()
                ->indexWidth(100)
                ->detailWidth(20)
                ->maxWidth(100)
                ->textAlign('center')
                ->prunable(),
            ImageCropper::make('Banner')
                ->croppable()
                ->resize(1280)
                ->convert('webp')
                ->disk('do')
                ->nullable()
                ->indexWidth(100)
                ->detailWidth(20)
                ->maxWidth(100)
                ->textAlign('center')
                ->prunable()->hideFromIndex(),
            Textarea::make('about')
                ->nullable()
                ->rules('nullable', 'string', 'max:2000')
                ->hideFromIndex(),
            Color::make('Brand Colour', 'brand_colour'),
            Color::make('Accent colour', 'accent_colour1'),
            Color::make('Accent colour #2', 'accent_colour2')->hideFromIndex(),
            Color::make('Brand Background Colour', 'brand_background_colour')->hideFromIndex(),
            HasMany::make('Events', 'listings', Listing::class),
            MorphMany::make('Gig Widgets', 'widgets', Widget::class),
        ];
    }

    /**
     * Get the cards available for the resource.
     *
     * @return array<int, Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
