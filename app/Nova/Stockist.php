<?php

namespace App\Nova;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * @extends \App\Nova\Resource<\App\Models\Stockist>
 */
class Stockist extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Stockist>
     */
    public static $model = \App\Models\Stockist::class;

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

    public static $defaultSort = 'name';

    public static $defaultSortDirection = 'asc'; // or 'desc'

    public static $redirectAfterUpdate = 'index';

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Name')->sortable()->rules('required', 'max:255'),
            ImageCropper::make('Cover photo')
                ->croppable(600 / 400)
                ->resize(300, 200)
                ->convert('webp')
                ->disk('do')
                ->indexWidth(150)
                ->prunable()
                ->nullable(),
            Text::make('Address'),
            Text::make('Town')->sortable(),
            Text::make('Postcode'),
            Text::make('Link')->nullable(),
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
