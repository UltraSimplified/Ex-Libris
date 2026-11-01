<?php

namespace App\Nova;

use Jacobfitzp\NovaCanvas\Canvas;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Ultrasimplified\ImageCropper\ImageCropper;
use Whitecube\NovaFlexibleContent\Flexible;

/**
 * @extends \App\Nova\Resource<\App\Models\Page>
 */
class Page extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Page>
     */
    public static $model = \App\Models\Page::class;

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
        'title',
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
            Text::make('Title')->sortable()->rules('required'),
            Text::make('Slug')
                ->sortable()
                ->rules('required', function ($attribute, $value, $fail) {
                    if (str_contains($value, ' ')) {
                        return $fail('The '.$attribute.' cannot contain spaces.');
                    }

                    return true;
                }),
            ImageCropper::make('Hero image')->convert('webp')->disk('do')->required()->indexWidth(300)->prunable(),
            Flexible::make('Body')
                ->addLayout('Text block', 'copy', [
                    Canvas::make('Text'),
                ])
                ->addLayout('Pull quote', 'pullquote', [
                    Textarea::make('quote'),
                    Text::make('cite'),
                ])
                ->addLayout('Left image', 'imgleft', [
                    Image::make('Image')->disk('do')->prunable(),
                    Textarea::make('alt'),
                    Text::make('caption'),
                    Canvas::make('Text'),
                ])
                ->addLayout('Right image', 'imgright', [
                    Canvas::make('Text'),
                    ImageCropper::make('Image')->disk('do')->prunable(),
                    Textarea::make('alt'),
                    Text::make('caption'),
                ])
                ->addLayout('Full width image', 'imgfull', [
                    ImageCropper::make('Image')->disk('do')->prunable(),
                    Textarea::make('alt'),
                    Text::make('caption'),
                ])
                ->addLayout('Code embed', 'embed', [Textarea::make('code')])
                ->addLayout('2 Columns Text', 'twocolumns', [
                    Canvas::make('Col1 Text'),
                    Canvas::make('Col2 Text'),
                ])
                ->addLayout('Divider', 'divider', [])
                ->addLayout('Spacer', 'spacer', [
                    // Defaulted, so a spacer added and saved without the
                    // select being touched still takes up room.
                    Select::make('Padding')->options([
                        '20px' => '20px',
                        '40px' => '40px',
                        '60px' => '60px',
                        '80px' => '80px',
                    ])->default('40px'),
                ])
                ->addLayout('Colophon', 'colophon', [
                    Canvas::make('Col1 Text'),
                    Canvas::make('Col2 Text'),
                ])
                ->confirmRemove()
                ->fullWidth(),

            $this->socialMediaPanel('the title', 'the opening of the page', 'the hero image'),
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
