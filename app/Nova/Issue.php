<?php

namespace App\Nova;

use Illuminate\Support\Facades\Storage;
use Jacobfitzp\NovaCanvas\Canvas;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * @extends resource<\App\Models\Issue>
 */
class Issue extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Issue>
     */
    public static $model = \App\Models\Issue::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'month_year';

    /**
     * An issue by its number and month, as a writer's Issues field lists
     * it, so one can be told from another at a glance.
     */
    public function title(): string
    {
        return "Issue {$this->resource->issue_number} — {$this->resource->month_year}";
    }

    /**
     * The picture an issue is shown with in a writer's Issues field: its
     * cover, which every issue has, rather than Nova's pick of the first
     * image - the hero, which only the newer issues have.
     */
    public function resolveAvatarUrl(NovaRequest $request): ?string
    {
        return filled($this->resource->cover_photo)
            ? Storage::disk('do')->url($this->resource->cover_photo)
            : null;
    }

    /**
     * The column to order issues by.
     *
     * @var array
     */
    public static $indexDefaultOrder = [
        'issue_number' => 'desc',
    ];

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'issue_number',
        'month_year',
        'tagline',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Number::make('Issue number', 'issue_number')->required(),
            Text::make('Month & Year', 'month_year')->default(date('F Y'))->required(),
            Canvas::make('Tagline', 'tagline')->nullable()->sortable()->hideFromIndex(),
            ImageCropper::make('Hero Image')->croppable(1500 / 1876)->resize(1500, 1876)->convert('webp')->disk('do')->required()->indexWidth(300)->maxWidth(100)->withMeta(['textAlign' => 'center'])->prunable(),
            Textarea::make('Alt text')->nullable(),
            ImageCropper::make('Cover photo')->croppable(256 / 360)->resize(256, 360)->convert('webp')->disk('do')->required()->indexWidth(300)->maxWidth(100)->withMeta(['textAlign' => 'center'])->prunable(),
            Text::make('Cover artist', 'cover_artist')->nullable(),
            Text::make('Playlist (link)', 'playlist')->nullable()->displayUsing(function ($value) {
                if (! empty($value)) {
                    return '✅';
                }
            }),
            File::make('PDF')->required()->hideFromIndex()->disk('do'),
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
