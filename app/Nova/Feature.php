<?php

namespace App\Nova;

use App\Nova\Concerns\ShowsLayoutImages;
use App\Rules\Embed;
use App\Services\Images\FeatureGallery;
use App\Services\Images\FeatureImages;
use App\Support\FeatureStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Jacobfitzp\NovaCanvas\Canvas;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Outl1ne\NovaSortable\Traits\HasSortableRows;
use Ultrasimplified\Gallery\Gallery;
use Ultrasimplified\ImageCropper\ImageCropper;
use Ultrasimplified\VideoEmbed\VideoEmbed;
use Whitecube\NovaFlexibleContent\Flexible;

/**
 * Features are dragged into order on the index, newest at the top to begin
 * with. The featured ones lead the home page in that order.
 *
 * @extends resource<\App\Models\Feature>
 */
class Feature extends Resource
{
    use HasSortableRows;
    use ShowsLayoutImages;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Feature>
     */
    public static $model = \App\Models\Feature::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'subject';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'title', 'subject', 'published', 'tag.tag',
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
            /*
             * A new feature starts as a draft, and a draft saves however
             * far it has got: only the title is needed to file it. A
             * published feature stays published when it is edited, and can
             * be put back to a draft. Publishing asks for everything the
             * site shows - see afterValidation().
             */
            Select::make('Status')
                ->options(FeatureStatus::options())
                ->default(FeatureStatus::Draft->value)
                ->resolveUsing(fn ($status) => $status instanceof FeatureStatus ? $status->value : $status)
                ->displayUsingLabels()
                ->rules('required', Rule::enum(FeatureStatus::class))
                ->help('A draft is seen only by the Office, marked as a draft. Publish it once it has a subject, date, tag, both pictures and an excerpt.')
                ->hideFromIndex(),
            Badge::make('Status')
                ->resolveUsing(fn ($status) => $status instanceof FeatureStatus ? $status->value : $status)
                ->map([FeatureStatus::Draft->value => 'warning', FeatureStatus::Published->value => 'success'])
                ->labels(FeatureStatus::options())
                ->onlyOnIndex(),
            Text::make('Title')->hideFromIndex()->sortable()->rules('required'),
            Text::make('Subject')->sortable()->nullable(),
            Date::make('Date Published', 'published')->sortable()->nullable(),
            Boolean::make('Featured')
                ->sortable()
                ->help('Show at the top of the home page. Feature more than one and they scroll as a carousel, in the order they are dragged into on the Features list; feature none and the newest feature goes there.'),
            Number::make('Issue number')->sortable()->nullable()->hideFromIndex(),
            BelongsTo::make('Tag')->nullable(),
            Select::make('Colourway')->options(['white' => 'White', 'hi-vis' => 'Hi-vis', 'slate' => 'Slate', 'key-lime' => 'Key Lime'])->default('white'),
            ImageCropper::make('Cover photo')->croppable(640 / 397)->resize(1280, 794)->convert('webp')->disk('do')->indexWidth(300)->prunable(),
            Textarea::make('Alt', 'cover_alt_text'),
            ImageCropper::make('Feature image')->croppable(748 / 794)->resize(748, 794)->convert('webp')->disk('do')->indexWidth(300)->prunable()->hideFromIndex(),
            Textarea::make('Alt', 'feature_alt_text'),
            Flexible::make('Body')
                ->addLayout('Text block', 'copy', [
                    Canvas::make('Text'),
                ])
                ->addLayout('Pull quote', 'pullquote', [
                    Textarea::make('quote'),
                    Text::make('cite'),
                ])
                ->addLayout('Left image', 'imgleft', [
                    $this->layoutImage(Image::make('Image'), FeatureImages::DISK),
                    Text::make('link'),
                    Textarea::make('alt'),
                    Text::make('caption'),
                    Canvas::make('Text'),
                ])
                ->addLayout('Right image', 'imgright', [
                    Canvas::make('Text'),
                    $this->layoutImage(ImageCropper::make('Image'), FeatureImages::DISK),
                    Text::make('link'),
                    Textarea::make('alt'),
                    Text::make('caption'),
                ])
                ->addLayout('Full width image', 'imgfull', [
                    $this->layoutImage(ImageCropper::make('Image'), FeatureImages::DISK),
                    Text::make('link'),
                    Textarea::make('alt'),
                    Text::make('caption'),
                ])
                ->addLayout('2 Columns Text', 'twocolumns', [
                    Canvas::make('Col1 Text'),
                    Canvas::make('Col2 Text'),
                ])
                ->addLayout('Divider', 'divider', [])
                ->addLayout('Colophon', 'colophon', [
                    Canvas::make('Col1 Text'),
                    Canvas::make('Col2 Text'),
                ])
                ->addLayout('Video', 'video', [
                    VideoEmbed::make('Video', 'video')
                        ->help('Paste the embed code from YouTube or Vimeo (Share, then Embed). The video shows above once it is recognised.'),
                ])
                ->addLayout('Photojournal', 'gallery', [
                    Gallery::make('Images')
                        ->disk(FeatureGallery::DISK)
                        ->path(FeatureGallery::DIRECTORY)
                        ->maxEdge(FeatureGallery::MAX_EDGE)
                        ->thumbnailEdge(FeatureGallery::THUMBNAIL_EDGE)
                        ->maxImages(FeatureGallery::MAX_IMAGES)
                        ->help('Choose up to '.FeatureGallery::MAX_IMAGES.' photos, as many at once as you like. Wait for them all to upload before saving.'),
                ])
                ->addLayout('Audio Playlist', 'playlist', [
                    Textarea::make('playlist')
                        ->rules(Embed::audio())
                        ->help('Paste the whole embed code from Bandcamp, Spotify, Tidal or Qobuz.'),
                ])
                ->confirmRemove()
                ->fullWidth(),
            Textarea::make('Excerpt')->nullable(),
            /*
             * Credited to the account that wrote it. Held to the writers
             * so the byline cannot quietly be given to a venue manager,
             * and still labelled Writer because that is what the desk
             * calls it. A new writer can be made from here, and a feature
             * with no writer is left empty rather than credited to anyone.
             */
            BelongsTo::make('Writer', 'user', User::class)
                ->relatableQueryUsing(
                    fn (NovaRequest $request, Builder $query) => $query->where('is_writer', true)
                )
                ->showCreateRelationButton()
                ->searchable()
                ->nullable(),

            /*
             * The event the feature is about, in a section of its own and
             * shown at the foot of the feature as it is on the listings
             * pages. Searched by title or venue, with the venue and date
             * beside each so repeat events can be told apart.
             */
            Panel::make('Listing', [
                BelongsTo::make('Listing')
                    ->searchable()
                    ->withSubtitles()
                    ->nullable()
                    ->hideFromIndex()
                    ->help('Show this event at the foot of the feature, as it appears on the listings page.'),
            ]),

            $this->socialMediaPanel('the title', 'the excerpt', 'the cover photo'),
        ];
    }

    /**
     * What a feature must have before it is published, by what each is
     * sent as and what the editor calls it. Every one is shown on the
     * site; a draft may go without any of them.
     *
     * @var array<string, string>
     */
    private const NEEDED_TO_PUBLISH = [
        'subject' => 'a subject',
        'published' => 'a date published',
        'tag' => 'a tag',
        'cover_photo' => 'a cover photo',
        'feature_image' => 'a feature image',
        'excerpt' => 'an excerpt',
    ];

    /**
     * A feature is only published once it is complete. A picture counts
     * if one is sent or the feature already has one, since an edit that
     * leaves a picture alone does not send it again.
     */
    protected static function afterValidation(NovaRequest $request, $validator): void
    {
        if ($request->input('status') !== FeatureStatus::Published->value) {
            return;
        }

        $feature = $request->resourceId ? $request->findModel() : null;

        foreach (self::NEEDED_TO_PUBLISH as $attribute => $label) {
            $isPicture = in_array($attribute, ['cover_photo', 'feature_image'], true);

            $isGiven = filled($request->input($attribute))
                || ($isPicture && ($request->hasFile($attribute) || filled($feature?->{$attribute})));

            if (! $isGiven) {
                $validator->errors()->add($attribute, "Add {$label} to publish this feature, or save it as a draft.");
            }
        }
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
