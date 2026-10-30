<?php

namespace App\Nova;

use App\Nova\Actions\ExportListings;
use App\Nova\Concerns\ShowsLayoutImages;
use App\Nova\Filters\ListingPeriod;
use App\Rules\Embed;
use App\Services\Listings\ListingLineUp;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Resource as NovaResource;
use Laravel\Nova\URL;
use Ultrasimplified\ImageCropper\ImageCropper;
use Whitecube\NovaFlexibleContent\Flexible;

class Listing extends Resource
{
    use ShowsLayoutImages;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Listing>
     */
    public static $model = \App\Models\Listing::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'title';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'title', 'body', 'venue.name',
    ];

    /**
     * The venue and first date, shown under the title where a listing is
     * picked from a search, as it is on a feature: the same event is often
     * listed more than once.
     */
    public function subtitle(): ?string
    {
        return collect([$this->venue?->name, $this->start_date?->format('j M Y')])
            ->filter()
            ->implode(' · ') ?: null;
    }

    /**
     * Return the location to redirect the user after create.
     *
     * @return URL|string
     */
    public static function redirectAfterCreate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/listings/';
    }

    /**
     * Return the location to redirect the user after update.
     *
     * @return URL|string
     */
    public static function redirectAfterUpdate(NovaRequest $request, NovaResource $resource)
    {
        return '/resources/listings/';
    }

    /**
     * Build an "index" query for the given resource.
     *
     * Listings are worked through in date order, so default to that instead
     * of newest id first. An explicit sort from the user still wins.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        if (empty($request->get('orderBy'))) {
            $query->getQuery()->orders = [];

            return $query->orderBy('start_date');
        }

        return $query;
    }

    /**
     * Return a replicated resource.
     *
     * The slug is cleared so the copy regenerates one from whatever title it
     * is saved with; keeping the original would give two listings the same
     * slug. Dates are kept, since a duplicate is nearly always the same event
     * being moved on rather than a different event.
     *
     * @return static
     */
    public function replicate()
    {
        return tap(parent::replicate(), function ($resource) {
            $resource->model()->slug = null;
        });
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field>
     *
     * video playlist
     * gallery
     * venue_id
     * category_id
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Date::make('Start date')
                ->rules('required')
                ->sortable()
                ->displayUsing(function ($date) {
                    $display_date = explode(' ', $date);

                    return date('d M Y', strtotime($display_date[0]));
                }),
            Date::make('End date')
                ->nullable()
                ->hideFromIndex()
                ->displayUsing(function ($date) {
                    $display_date = explode(' ', $date);

                    return date('d M Y', strtotime($display_date[0]));
                }),
            Text::make('Additional date information', 'additional_date_information')->nullable()->hideFromIndex()->help('eg. Tuesday and Thursday mornings only'),
            Text::make('Time description', 'event_time_description')->nullable()
                ->hideFromIndex(),
            Text::make('Title')->sortable()->rules('required'),
            BelongsTo::make('Category')
                ->required()
                ->hideFromIndex(),
            ImageCropper::make('Cover photo')
                ->croppable(1246 / 758)
                ->resize(1246, 758)
                ->convert('webp')
                ->disk('do')
                ->prunable()
                ->hideFromIndex(),
            ImageCropper::make('Thumbnail')
                ->croppable(548 / 462)
                ->resize(548, 462)
                ->convert('webp')
                ->disk('do')
                ->indexWidth(300)
                ->prunable(),
            Text::make('Ticket Price', 'ticket_price')
                ->help('Enter price, Pay What You Feel, Donation or leave blank if no tickets needed')->nullable()
                ->hideFromIndex(),

            Boolean::make('Sold out', 'sold_out')
                ->help('Flashed across the event\'s image on the site')
                ->hideFromIndex(),

            Text::make('Button text', 'button_text')->default('Buy Tickets')->hideFromIndex(),

            Textarea::make('Body')->rules('required')->hideFromIndex(),

            BelongsTo::make('Venue')
                ->nullable()
                ->showCreateRelationButton()
                ->sortable(),

            BelongsTo::make('Promoter')
                ->nullable()
                ->showCreateRelationButton()
                ->sortable()
                ->help('Who is putting the event on, where that is not the venue itself.'),

            Text::make('Event booking link', 'event_link')
                ->help('only fill this in if the booking address is different to the venue address')
                ->nullable()
                ->hideFromIndex(),

            /*
             * The artists on the bill, each with a playlist, a video or
             * both, shown in this order. The listing cleans every embed as
             * it saves, keeping only named artists with something to play.
             */
            Flexible::make('Line-up', 'line_up')
                ->button('Add artist')
                ->addLayout('Artist', 'artist', [
                    Text::make('Name')
                        ->rules('required', 'max:'.ListingLineUp::MAX_NAME),
                    Textarea::make('Playlist embed', 'playlist')
                        ->nullable()
                        ->rules('nullable', Embed::audio())
                        ->help('A Bandcamp, Spotify, Tidal or Qobuz embed code.'),
                    Textarea::make('Video embed', 'video')
                        ->nullable()
                        ->rules('nullable', Embed::video())
                        ->help('A YouTube or Vimeo embed code or link. An artist needs a playlist, a video or both.'),
                ])
                ->limit(ListingLineUp::MAX_ARTISTS)
                ->confirmRemove()
                ->fullWidth(),

            Flexible::make('Gallery')
                ->button('Add Image')
                ->addLayout('Image', 'image', [
                    $this->layoutImage(Image::make('Image', 'image'), 'do')
                        ->store(function ($request, $model, $attribute, $requestAttribute) {
                            return $request->file($requestAttribute)
                                ->store('gallery', 'do');
                        }),
                    Text::make('Caption')->nullable(),
                ])
                ->confirmRemove()
                ->fullWidth(),
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
        return [
            ListingPeriod::make(),
        ];
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
            ExportListings::make()->standalone(),
        ];
    }
}
