<?php

namespace App\Nova\Cards;

use App\Models\Category;
use App\Models\Listing;
use App\Nova\Concerns\MakesDonutSlices;
use App\Services\Listings\ListingCsvExport;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Card;

/**
 * The listings coming up, by category and by month, over a one-click
 * download of the listings CSV for the issue being laid out.
 *
 * The Nova action asks for a range; the download asks for nothing and
 * assumes the usual one, so the layout artist can pull the file straight
 * off the dashboard. The link is signed when the card renders and is good
 * for two hours, which comfortably outlives the page.
 */
class ListingsExport extends Card
{
    use MakesDonutSlices;

    public $width = '1/2';

    /**
     * How many months the month chart names before gathering the rest as
     * later: this one and the four after it, one slice for every colour.
     */
    private const MONTHS = 5;

    public function component(): string
    {
        return 'gop-listings-export';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $from = now()->addMonthNoOverflow()->startOfMonth();
        $to = now()->addMonthNoOverflow()->endOfMonth();
        $month = $from->format('F');

        return array_merge(parent::jsonSerialize(), [
            'heading' => 'Listings',
            'charts' => [
                ['heading' => 'Upcoming by category', 'slices' => $this->byCategory()],
                ['heading' => 'Upcoming by month', 'slices' => $this->byMonth()],
            ],
            'emptyText' => 'No listings are coming up.',
            'exportHeading' => 'Download listings for the '.$month.' issue',
            'count' => Listing::query()->runningBetween($from, $to)->count(),
            'countLabel' => 'Listings',
            'buttonText' => 'Download '.$month.' listings',
            'filename' => app(ListingCsvExport::class)->filename($from, $to),
            'url' => URL::temporarySignedRoute('exports.listings', now()->addHours(2), [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]),
            'link' => '/resources/listings',
            'linkText' => 'View all current listings',
        ]);
    }

    /**
     * The listings still to come or running today, by category, the most
     * first. A listing with no category is counted rather than dropped.
     *
     * @return array<int, array{label: string, value: int, colour: string}>
     */
    private function byCategory(): array
    {
        $names = Category::pluck('name', 'id');

        return $this->donutSlices(Listing::query()
            ->current()
            ->selectRaw('category_id, count(*) as listings')
            ->groupBy('category_id')
            ->orderByDesc('listings')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'label' => $names[$row->category_id] ?? 'Uncategorised',
                'value' => (int) $row->listings,
            ]));
    }

    /**
     * The same listings by the month they are on: this month and the four
     * after it by name, in order, and everything further off as later. A
     * listing that began before today is still on this month, so it counts
     * there rather than in the month it started. A month with nothing on is
     * left out, having nothing to draw.
     *
     * Each listing is counted once, so the slices add up to everything coming
     * up. The download for an issue takes everything on at any point in its
     * month, so it also has the listings that began earlier and run into it,
     * and its number is higher than that month's slice.
     *
     * @return array<int, array{label: string, value: int, colour: string}>
     */
    private function byMonth(): array
    {
        $thisMonth = today()->startOfMonth();
        $lastNamed = $thisMonth->copy()->addMonthsNoOverflow(self::MONTHS - 1)->format('Y-m');

        $counts = Listing::query()
            ->current()
            ->selectRaw(
                'case when date(start_date) < ? then ? else substr(start_date, 1, 7) end as listed_month, count(*) as listings',
                [today()->toDateString(), $thisMonth->format('Y-m')]
            )
            ->groupBy('listed_month')
            ->toBase()
            ->pluck('listings', 'listed_month')
            ->map(fn (mixed $listings): int => (int) $listings);

        return $this->donutSlices(collect(range(0, self::MONTHS - 1))
            ->map(fn (int $ahead): CarbonInterface => $thisMonth->copy()->addMonthsNoOverflow($ahead))
            ->map(fn (CarbonInterface $month): array => [
                'label' => $month->format('F'),
                'value' => $counts->get($month->format('Y-m'), 0),
            ])
            ->push([
                'label' => 'Later',
                'value' => $counts->filter(fn (int $listings, string $month): bool => $month > $lastNamed)->sum(),
            ])
            ->filter(fn (array $slice): bool => $slice['value'] > 0));
    }
}
