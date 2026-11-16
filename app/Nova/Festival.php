<?php

namespace App\Nova;

use App\Nova\Fields\BrandProfileFields;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\MorphMany;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A promoter's festival, as the Office keeps it. Staff reach every
 * festival here whether or not they run festivals in their own Backstage.
 *
 * @extends \App\Nova\Resource<\App\Models\Festival>
 */
class Festival extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Festival>
     */
    public static $model = \App\Models\Festival::class;

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
     * Get the fields displayed by the resource.
     *
     * The promoter is chosen when the festival is made and stays: its
     * listings are all that promoter's, so moving it would strand them.
     *
     * @return array<int, Field>
     *
     * @throws \Exception
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Name')->sortable()->rules('required', 'max:255'),
            BelongsTo::make('Promoter')
                ->sortable()
                ->readonly(fn (NovaRequest $request): bool => $request->isUpdateOrUpdateAttachedRequest())
                ->help('The promoter putting it on. It cannot be changed once the festival is made.'),
            ...(new BrandProfileFields)->fields(),
            HasMany::make('Events', 'listings', Listing::class),
            MorphMany::make('Gig Widgets', 'widgets', Widget::class),
        ];
    }
}
