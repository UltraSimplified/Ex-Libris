<?php

namespace App\Nova\Fields;

use App\Models\Listing;
use App\Models\ListingDay;
use App\Rules\SpecificDaysWithinRun;
use App\Services\Listings\ListingDays;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Support\Fluent;
use Ultrasimplified\SpecificDays\SpecificDays;

/**
 * When a listing is on, as the Office shows and edits it: its run, its
 * specific days and what the dates cannot say.
 *
 * The specific days are ticked on the same calendar as the Dashboard's and
 * saved by the same rules (ListingDays): the run is pulled in to the first
 * and last day ticked, and the days are written once Nova has saved the
 * listing. The calendar comes after the dates, so it is filled after them
 * and can move them.
 */
final class ListingDateFields
{
    public function __construct(private Listing $listing) {}

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Date::make('Start date')
                ->rules('required')
                ->sortable()
                ->displayUsing(fn (mixed $date): string => $this->displayDate($date)),
            Date::make('End date')
                ->nullable()
                ->hideFromIndex()
                ->displayUsing(fn (mixed $date): string => $this->displayDate($date)),
            SpecificDays::make('Specific days', 'specific_days')
                ->resolveUsing(fn (): array => $this->chosenDays())
                ->displayUsing(fn (): ?string => $this->daysListed())
                ->hideFromIndex()
                ->showOnDetail(fn (): bool => $this->listing->days->isNotEmpty())
                ->rules(new SpecificDaysWithinRun)
                ->fillUsing(fn (NovaRequest $request, Model|Fluent $model, string $attribute, string $requestAttribute): ?Closure => $model instanceof Listing && $request->exists($requestAttribute)
                    ? $this->fillDays($request->input($requestAttribute), $model)
                    : null),
            Text::make('Additional date information', 'additional_date_information')->nullable()->hideFromIndex()->help('eg. Tuesday and Thursday mornings only'),
        ];
    }

    /**
     * A date as the Office lists it, "05 Nov 2026".
     */
    private function displayDate(mixed $date): string
    {
        $display_date = explode(' ', (string) $date);

        return date('d M Y', strtotime($display_date[0]));
    }

    /**
     * The listing's days as the calendar takes them: ticked when it has any.
     *
     * @return array{enabled: bool, days: list<string>}
     */
    private function chosenDays(): array
    {
        $days = $this->listing->days
            ->map(fn (ListingDay $day): string => $day->day->toDateString())
            ->values()
            ->all();

        return ['enabled' => $days !== [], 'days' => $days];
    }

    /**
     * The specific days, as "3 Nov, 17 Nov, 1 Dec, 15 Dec 2026". A year is
     * given once when they all fall in it, and on each day when they do not.
     */
    private function daysListed(): ?string
    {
        $days = $this->listing->days->map(fn (ListingDay $day): CarbonInterface => $day->day);

        if ($days->isEmpty()) {
            return null;
        }

        $oneYear = $days->first()->year === $days->last()->year;
        $listed = $days->map(fn (CarbonInterface $day): string => $day->format($oneYear ? 'j M' : 'j M Y'))->implode(', ');

        return $oneYear ? $listed.' '.$days->last()->year : $listed;
    }

    /**
     * Pull the run in to the days ticked, now, and hand Nova the writing of
     * the days for once the listing is saved. A save that did not send the
     * calendar at all leaves the days as they were, rather than reading its
     * absence as unticked.
     *
     * @return Closure(): void
     */
    private function fillDays(mixed $value, Listing $model): Closure
    {
        $listingDays = app(ListingDays::class);

        ['attributes' => $dates, 'days' => $days] = $listingDays->normalise([
            'start_date' => $model->start_date->toDateString(),
            'end_date' => $model->end_date?->toDateString(),
        ], SpecificDaysWithinRun::chosen($value)['days']);

        $model->forceFill($dates);

        return fn () => $listingDays->writeDays($model, $days);
    }
}
