<?php

namespace App\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Laravel\Nova\Resource as NovaResource;
use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends NovaResource<TModel>
 */
abstract class Resource extends NovaResource
{
    /**
     * Clicking a row opens the form rather than the read-only detail page.
     *
     * Everything in the Office is here to be changed, so the detail page
     * was only ever a stop on the way to the edit screen. Set once here so
     * a new resource behaves like the rest without being told to.
     */
    public static $clickAction = 'edit';

    /**
     * What a feature or a page shows when it is shared. Each value is
     * optional and falls back to the record's own; the help says to what.
     * The image is saved as a JPEG at 1200 by 630, the size every network
     * shows whole; WebP, which the record's own images are, is not taken
     * everywhere.
     */
    protected function socialMediaPanel(string $titleFallback, string $descriptionFallback, string $imageFallback): Panel
    {
        return Panel::make('Social media', [
            Text::make('Social title')
                ->nullable()
                ->rules('nullable', 'max:255')
                ->hideFromIndex()
                ->help("Shown as the headline when it is shared. Leave empty to use {$titleFallback}."),
            Textarea::make('Social description')
                ->nullable()
                ->rules('nullable', 'max:300')
                ->hideFromIndex()
                ->help("The line under the headline. Leave empty to use {$descriptionFallback}. Around 160 characters shows in full."),
            ImageCropper::make('Social image')
                ->croppable(1200 / 630)
                ->resize(1200, 630)
                ->convert('jpg')
                ->disk('do')
                ->nullable()
                ->prunable()
                ->hideFromIndex()
                ->help("Cropped to 1200 × 630. Leave empty to use {$imageFallback}."),
        ]);
    }

    /**
     * The order a resource's records come in until somebody sorts by a
     * column, as column => direction: on its own index, in a relation's
     * table on another record, and in an attach picker's list. Left empty,
     * Nova's own order stands - newest first.
     *
     * @var array<string, string>
     */
    public static $indexDefaultOrder = [];

    /**
     * Apply the default orderings for the given resource.
     */
    public static function defaultOrderings(Builder $query): Builder
    {
        if (static::$indexDefaultOrder === []) {
            return parent::defaultOrderings($query);
        }

        foreach (static::$indexDefaultOrder as $column => $direction) {
            $query->orderBy($query->getModel()->qualifyColumn($column), $direction);
        }

        return $query;
    }

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Build a "detail" query for the given resource.
     */
    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::detailQuery($request, $query);
    }

    /**
     * Build a "relatable" query for the given resource.
     *
     * This query determines which instances of the model may be attached to other resources.
     */
    public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        $query = parent::relatableQuery($request, $query);

        return static::$indexDefaultOrder === [] ? $query : static::defaultOrderings($query);
    }
}
