<?php

namespace App\Nova;

use App\Nova\Actions\DeleteVenue;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Jacobfitzp\NovaCanvas\Canvas;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\MorphMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Outl1ne\NovaColorField\Color;
use Ultrasimplified\ImageCropper\ImageCropper;

class Venue extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Venue>
     */
    public static $model = \App\Models\Venue::class;

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

    public static $sort = [
        'name' => 'asc',
    ];

    /**
     * Count each venue's listings with the page, so whether it may be
     * deleted outright costs no query a row.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withCount('listings');
    }

    /**
     * A venue with listings is not deleted outright, which the database
     * would refuse anyway; Delete Venue offers to move them first.
     */
    public function authorizedToDelete(Request $request): bool
    {
        if (! parent::authorizedToDelete($request)) {
            return false;
        }

        return ! ($this->resource->listings_count ?? $this->resource->listings()->exists());
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Name')->sortable(),
            BelongsTo::make('Owner', 'user', User::class)
                ->nullable()
                ->searchable()
                ->help('The account that can edit this venue, and its listings, from the front-end dashboard.'),
            Text::make('Address')->nullable(),
            Text::make('Town')->nullable(),
            Text::make('postcode')->nullable(),
            // Found from the postcode when the venue is saved, so shown on
            // the venue's page in the Office but kept off its form.
            Number::make('Latitude')
                ->step(0.0000001)
                ->onlyOnDetail(),
            Number::make('Longitude')
                ->step(0.0000001)
                ->onlyOnDetail(),
            ImageCropper::make('Cover photo')->croppable()->resize(1280)->convert('webp')->disk('do')->indexWidth(300)->prunable()->nullable(),
            Canvas::make('Description')
                ->nullable()
                ->hideFromIndex()
                ->help('Shown under the cover photo at the top of the venue\'s page of listings.'),
            Text::make('url')
                ->nullable()->displayUsing(function ($value) {
                    if (! empty($value)) {
                        return '🔗';
                    } else {
                        return '';
                    }
                }),
            Text::make('Phone')
                ->nullable()
                ->rules('nullable', 'string', 'max:30')
                ->hideFromIndex()
                ->help('Shown under the website on the venue\'s page of listings.'),
            Email::make('Email')
                ->nullable()
                ->rules('nullable', 'email', 'max:255')
                ->hideFromIndex()
                ->help('Shown under the phone number on the venue\'s page of listings.'),
            Boolean::make('Stockist'),
            // What the venue's gig widgets are painted in, as on a promoter.
            Color::make('Brand Colour', 'brand_colour')->nullable()->hideFromIndex(),
            Color::make('Accent colour', 'accent_colour1')->nullable()->hideFromIndex(),
            Color::make('Accent colour #2', 'accent_colour2')->nullable()->hideFromIndex(),
            Color::make('Brand Background Colour', 'brand_background_colour')->nullable()->hideFromIndex(),
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
        return [
            DeleteVenue::make()
                ->showInline()
                // Run on staff's say, not on whether the plain delete is offered.
                ->canRun(fn (NovaRequest $request) => Gate::allows('viewNova')),
        ];
    }
}
