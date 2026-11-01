<?php

namespace App\Nova;

use App\Support\Widgets\WidgetDensity;
use App\Support\Widgets\WidgetFont;
use App\Support\Widgets\WidgetSizeMode;
use App\Support\Widgets\WidgetSnippets;
use App\Support\Widgets\WidgetTemplate;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;

/**
 * Gig widgets in the Office, where staff can make one for any venue -
 * including one nobody has claimed yet, as a reason to claim it. The
 * finer settings and the live preview are on the dashboard.
 *
 * @extends \App\Nova\Resource<\App\Models\Widget>
 */
class Widget extends Resource
{
    /**
     * @var class-string<\App\Models\Widget>
     */
    public static $model = \App\Models\Widget::class;

    /**
     * @var string
     */
    public static $title = 'name';

    /**
     * @var array
     */
    public static $search = [
        'name', 'key',
    ];

    public static function label(): string
    {
        return 'Gig Widgets';
    }

    public static function singularLabel(): string
    {
        return 'Gig Widget';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable()->hideFromIndex(),
            Text::make('Name')
                ->rules('required', 'max:100')
                ->help('For telling widgets apart, say "Home page" or "Footer". Never shown on the widget.'),
            MorphTo::make('Showing events from', 'owner')
                ->types([Venue::class, Promoter::class])
                ->searchable()
                ->hideFromIndex(),
            // On the list, the venue or promoter's name opens the widget
            // itself, as a venue would see it, rather than their record.
            Text::make('Showing events from', fn (): ?string => $this->resource->owner?->name)
                ->asHtml()
                ->displayUsing(fn (?string $name): ?string => $name === null ? null : sprintf(
                    '<a class="link-default" href="%s" target="_blank" rel="noopener">%s</a>',
                    e(route('widgets.embed', ['widget' => $this->resource->key])),
                    e($name),
                ))
                ->onlyOnIndex(),
            Select::make('Template')
                ->options($this->options(WidgetTemplate::cases()))
                ->default(WidgetTemplate::List->value)
                ->displayUsingLabels()
                ->rules('required'),
            Select::make('Style', 'density')
                ->options($this->options(WidgetDensity::cases()))
                ->default(WidgetDensity::Detailed->value)
                ->displayUsingLabels()
                ->rules('required'),
            Select::make('Typeface', 'font')
                ->options($this->options(WidgetFont::cases()))
                ->default(WidgetFont::Sans->value)
                ->displayUsingLabels()
                ->hideFromIndex(),
            Select::make('Size', 'size_mode')
                ->options($this->options(WidgetSizeMode::cases()))
                ->default(WidgetSizeMode::Auto->value)
                ->displayUsingLabels()
                ->hideFromIndex(),
            Number::make('Widest (px)', 'width')->nullable()->min(200)->max(2400)->hideFromIndex(),
            Number::make('Height (px)', 'height')->nullable()->min(100)->max(4000)->hideFromIndex(),
            Boolean::make('Switched on', 'active')->default(true),
            Text::make('Key')->exceptOnForms()->copyable()->hideFromIndex(),
            Textarea::make('Snippet', fn (): ?string => $this->resource->exists ? WidgetSnippets::for($this->resource)['script'] : null)
                ->onlyOnDetail()
                ->alwaysShow()
                ->help('Paste into the venue\'s website. Settings and the preview are on the dashboard.'),
            Text::make('Dashboard', fn (): ?string => $this->resource->exists ? route('dashboard.widgets.edit', $this->resource) : null)
                ->asHtml()
                ->displayUsing(fn (?string $url): ?string => $url ? '<a class="link-default" href="'.e($url).'" target="_blank">Edit with preview</a>' : null)
                ->onlyOnDetail(),
            BelongsTo::make('Made by', 'user', User::class)->exceptOnForms()->nullable()->hideFromIndex(),
        ];
    }

    /**
     * @param  array<int, WidgetTemplate|WidgetDensity|WidgetFont|WidgetSizeMode>  $cases
     * @return array<string, string>
     */
    private function options(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn ($case): array => [$case->value => $case->label()])->all();
    }

    /**
     * @return array<int, Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return array<int, Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
