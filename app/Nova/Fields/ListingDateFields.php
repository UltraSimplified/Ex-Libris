<?php

namespace App\Nova\Fields;

use App\Models\Listing;
use App\Models\ListingDay;
use Carbon\CarbonInterface;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * When a listing is on, as the Office shows and edits it: its run, its
 * specific days and what the dates cannot say.
 *
 * Specific days are ticked in the Dashboard's calendar, which the Office has
 * no counterpart for, so here they are shown with a way there. While a
 * listing has them, its run is the first of them to the last, so the dates
 * are read-only too and cannot drift away from the days.
 */
final class ListingDateFields
{
    private const DATES_HELP = 'Set by the specific days, which are changed in the Dashboard.';

    public function __construct(private Listing $listing) {}

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        // Only asked on the edit form, so the index does not ask it per row.
        $datesHelp = $request->isUpdateOrUpdateAttachedRequest() && $this->datesFollowSpecificDays()
            ? self::DATES_HELP
            : '';

        return [
            Date::make('Start date')
                ->rules('required')
                ->readonly(fn (): bool => $this->datesFollowSpecificDays())
                ->help($datesHelp)
                ->sortable()
                ->displayUsing(fn (mixed $date): string => $this->displayDate($date)),
            Date::make('End date')
                ->nullable()
                ->readonly(fn (): bool => $this->datesFollowSpecificDays())
                ->help($datesHelp)
                ->hideFromIndex()
                ->displayUsing(fn (mixed $date): string => $this->displayDate($date)),
            Text::make('Specific days', fn (): ?string => $this->specificDaysHtml())
                ->asHtml()
                ->onlyOnDetail()
                ->showOnDetail(fn (): bool => $this->listing->days->isNotEmpty()),
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
     * Whether the listing's run is set by specific days, so is not for
     * changing here. A listing still being made has none yet.
     */
    private function datesFollowSpecificDays(): bool
    {
        return $this->listing->exists && $this->listing->days()->exists();
    }

    /**
     * The specific days, as "3 Nov, 17 Nov, 1 Dec, 15 Dec 2026", with a link
     * to change them in the Dashboard. A year is given once when they all
     * fall in it, and on each day when they do not.
     */
    private function specificDaysHtml(): ?string
    {
        $days = $this->listing->days->map(fn (ListingDay $day): CarbonInterface => $day->day);

        if ($days->isEmpty()) {
            return null;
        }

        $oneYear = $days->first()->year === $days->last()->year;
        $listed = $days->map(fn (CarbonInterface $day): string => $day->format($oneYear ? 'j M' : 'j M Y'))->implode(', ');

        if ($oneYear) {
            $listed .= ' '.$days->last()->year;
        }

        $edit = route('dashboard.listings.edit', $this->listing);

        return e($listed).'<br><a class="link-default" href="'.e($edit).'" target="_blank">Edit days in the Dashboard</a>';
    }
}
