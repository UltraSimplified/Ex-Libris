<?php

namespace App\Nova;

use App\Nova\Actions\MakeLive;
use App\Nova\Filters\AdvertSlot;
use App\Nova\Metrics\AdvertClicks;
use App\Nova\Metrics\AdvertCtr;
use App\Nova\Metrics\AdvertImpressions;
use App\Nova\Metrics\ClicksByLocality;
use App\Nova\Metrics\ImpressionsByLocality;
use App\Nova\Metrics\ImpressionsByVariant;
use App\Services\Adverts\AdvertReport;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * @extends resource<\App\Models\Advert>
 */
class Advert extends Resource
{
    public static $model = \App\Models\Advert::class;

    public static $title = 'title';

    public static $search = ['id', 'title', 'campaign', 'owner'];

    public static $clickAction = 'view';

    /**
     * The width and height each creative is uploaded at, which is the most
     * it is ever shown at.
     *
     * @var array{desktop: array{0: int, 1: int}, mobile: array{0: int, 1: int}}
     */
    public const CREATIVE_SIZES = [
        'desktop' => [728, 300],
        'mobile' => [400, 300],
    ];

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            // The PDF of this advert across its whole run, by a signed link
            // made as the page loads, as the Adverts card's is.
            Text::make('Report', function (): string {
                $report = app(AdvertReport::class);

                return sprintf(
                    '<a href="%s" download="%s" class="gop-report-button">Download report</a>',
                    e($report->downloadUrl($this->resource)),
                    e($report->filename($this->resource)),
                );
            })->asHtml()->onlyOnDetail(),

            Text::make('Title')
                ->rules('required', 'max:255')
                ->sortable(),

            Text::make('Campaign')
                ->nullable()
                ->hideFromIndex(),

            Text::make('Owner')
                ->nullable()
                ->sortable()
                ->help('Shown on a sponsorship as "Sponsored by …"; the title is used when this is empty.'),

            Badge::make('Slot', fn (): string => $this->resource->slot)
                ->map(['Sponsorship panel' => 'success', 'In-list' => 'info'])
                ->onlyOnIndex(),

            Panel::make('Creative', [
                $this->creative('Desktop Image', 'desktop', 'on screens 768px and wider, desktops and tablets'),

                $this->creative('Mobile Image', 'mobile', 'on phones, below 768px'),

                Text::make('Alt Text')
                    ->nullable()
                    ->hideFromIndex()
                    ->help('Describes the advert for screen readers'),

                Number::make('Impressions', fn () => $this->events()->impressions()->count())
                    ->onlyOnIndex()
                    ->sortable(false),

                Number::make('Clicks', fn () => $this->events()->clicks()->count())
                    ->onlyOnIndex()
                    ->sortable(false),

                URL::make('Link')
                    ->displayUsing(fn () => $this->link ? parse_url($this->link, PHP_URL_HOST) : null)
                    ->rules('nullable', 'url')
                    ->hideFromIndex()
                    ->help('Destination URL — UTM attribution is appended automatically'),
            ]),

            Panel::make('Scheduling', [
                Date::make('Run From')->nullable(),
                Date::make('Run To')
                    ->nullable()
                    ->rules('nullable', 'after_or_equal:run_from'),
                Boolean::make('Active')
                    ->help('Kill-switch — advert only serves when active AND within the run dates. Live adverts take page views in turn, and nobody sees the same one twice running.'),
                Boolean::make('Sponsorship')
                    ->help('Shown only in the panel at the foot of every page, in turn with the other live sponsorships, and never among the listings. Needs both creatives.'),
            ]),
            HasMany::make('Events', 'events', AdvertEvent::class),
        ];
    }

    /**
     * A creative's upload field.
     *
     * The file has to arrive at exactly its size, and is kept as it arrives:
     * nothing crops, scales or converts it, so an animated GIF stays
     * animated. The folder is not called adverts: ad blockers' filter lists
     * block any image under /adverts/, so readers using one saw only the
     * alt text.
     *
     * @param  'desktop'|'mobile'  $variant
     */
    private function creative(string $name, string $variant, string $shownOn): Image
    {
        [$width, $height] = self::CREATIVE_SIZES[$variant];

        return Image::make($name)
            ->disk('do')
            ->path('sponsors')
            ->prunable()
            ->acceptedTypes('image/gif,image/png,image/webp,image/jpeg')
            ->rules('nullable', 'mimes:gif,png,webp,jpg,jpeg', "dimensions:width={$width},height={$height}")
            ->hideFromIndex()
            ->help("Shown {$shownOn}, never more than {$width}×{$height}. Upload a GIF, PNG, WebP or JPEG at exactly {$width}×{$height} pixels; it is used as it is, so an animated GIF stays animated.");
    }

    public function cards(NovaRequest $request)
    {
        // Detail-page metrics: only show when viewing a single advert
        return [
            AdvertImpressions::make()->onlyOnDetail(),
            AdvertClicks::make()->onlyOnDetail(),
            AdvertCtr::make()->onlyOnDetail(),
            ImpressionsByLocality::make()->onlyOnDetail(),
            ClicksByLocality::make()->onlyOnDetail(),
            ImpressionsByVariant::make()->onlyOnDetail(),
        ];
    }

    public function filters(NovaRequest $request)
    {
        return [
            new AdvertSlot,
        ];
    }

    /**
     * A sponsorship fills the panel on phones and desktops alike, so it must
     * have both creatives: uploaded now, or already on the advert.
     */
    protected static function afterValidation(NovaRequest $request, $validator)
    {
        if (! $request->boolean('sponsorship')) {
            return;
        }

        $existing = $request->resourceId
            ? \App\Models\Advert::withTrashed()->find($request->resourceId)
            : null;

        foreach (['desktop_image' => 'desktop', 'mobile_image' => 'mobile'] as $attribute => $variant) {
            if (! $request->hasFile($attribute) && blank($existing?->{$attribute})) {
                $validator->errors()->add($attribute, "A sponsorship needs a {$variant} creative.");
            }
        }
    }

    public function actions(NovaRequest $request)
    {
        return [
            MakeLive::make()
                ->showInline(),
        ];
    }
}
