<?php

namespace App\Nova\Actions;

use App\Services\Listings\ListingCsvExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Exports every listing running in a chosen date range as a CSV.
 *
 * Standalone, because the range decides what is exported rather than
 * whatever happens to be ticked on the index.
 */
class ExportListings extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Export listings to CSV';

    public $confirmButtonText = 'Export';

    public $standalone = true;

    /**
     * Perform the action.
     *
     * The file is streamed by a signed route rather than written to disk, so
     * there is nothing to clean up afterwards.
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $from = $fields->from;
        $to = $fields->to;

        $url = URL::temporarySignedRoute('exports.listings', now()->addMinutes(10), [
            'from' => $from instanceof \DateTimeInterface ? $from->format('Y-m-d') : $from,
            'to' => $to instanceof \DateTimeInterface ? $to->format('Y-m-d') : $to,
        ]);

        return ActionResponse::download(app(ListingCsvExport::class)->filename($from, $to), $url);
    }

    /**
     * Get the fields available on the action.
     *
     * Defaults to next month, which is the issue being laid out: listings
     * close on the 13th, and the file is pulled on or around that date.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Date::make('From')
                ->rules('required', 'date')
                ->default(fn () => now()->addMonthNoOverflow()->startOfMonth()->toDateString())
                ->help('Listings still running on or after this date are included.'),

            Date::make('To')
                ->rules('required', 'date', 'after_or_equal:from')
                ->default(fn () => now()->addMonthNoOverflow()->endOfMonth()->toDateString())
                ->help('Listings that start on or before this date are included.'),
        ];
    }
}
