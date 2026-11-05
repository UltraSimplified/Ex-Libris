<?php

namespace App\Nova\Cards;

use App\Models\SiteVisit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Laravel\Nova\Card;

/**
 * Unique visitors to the site, and the pages they asked for, over the last
 * 24 hours, 7 days and 30 days. All three go to the page at once, so
 * switching between them is instant.
 */
class SiteVisits extends Card
{
    public $width = 'full';

    public function component(): string
    {
        return 'gop-site-visits';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'heading' => 'Unique Visitors',
            'requestsHeading' => 'Page Views',
            'ranges' => [
                $this->lastDayByHour(),
                $this->lastDays('7 Days', 7, 1, 'D'),
                $this->lastDays('30 Days', SiteVisit::DAYS_KEPT, 6, 'j M'),
            ],
        ]);
    }

    /**
     * The hour under way and the 23 before it, with an axis mark every four hours.
     *
     * @return array{label: string, period: string, total: int, requests: int, points: list<array{label: string, tick: ?string, visits: int, requests: int}>}
     */
    private function lastDayByHour(): array
    {
        $from = now()->startOfHour()->subHours(23);

        $hours = $this->totalsPerPeriod($from, 'visited_at', 'count(*)');

        $points = collect(range(0, 23))
            ->map(function (int $hoursOn) use ($from, $hours): array {
                $hour = $from->copy()->addHours($hoursOn);

                return $this->point(
                    $hour->format('D j M, g A'),
                    $hour->hour % 4 === 0 ? $hour->format('g A') : null,
                    $hours[$hour->toDateTimeString()] ?? null
                );
            })
            ->all();

        return $this->range('24 Hours', $from, $points);
    }

    /**
     * Whole days up to today, oldest first, counting a visitor once a day
     * however many hours they came in. The axis marks count back from today.
     *
     * @return array{label: string, period: string, total: int, requests: int, points: list<array{label: string, tick: ?string, visits: int, requests: int}>}
     */
    private function lastDays(string $label, int $days, int $daysBetweenTicks, string $tickFormat): array
    {
        $from = today()->subDays($days - 1);

        $daily = $this->totalsPerPeriod($from, 'date(visited_at)', 'count(distinct visitor_hash)');

        $points = collect(range($days - 1, 0))
            ->map(function (int $daysAgo) use ($daily, $daysBetweenTicks, $tickFormat): array {
                $day = today()->subDays($daysAgo);

                return $this->point(
                    $day->format('D j M'),
                    $daysAgo % $daysBetweenTicks === 0 ? $day->format($tickFormat) : null,
                    $daily[$day->toDateString()] ?? null
                );
            })
            ->all();

        return $this->range($label, $from, $points);
    }

    /**
     * Visitors and page views for every period that saw any, keyed by the
     * period. How a visitor is counted differs by period, so the caller
     * names the expression; page views are always every request summed.
     *
     * @return Collection<string, object{visitors: int, requests: int}>
     */
    private function totalsPerPeriod(CarbonInterface $from, string $period, string $visitors): Collection
    {
        /** @var Collection<string, object{visitors: int, requests: int}> */
        return SiteVisit::query()
            ->where('visited_at', '>=', $from)
            ->toBase()
            ->selectRaw("{$period} as period, {$visitors} as visitors, sum(requests) as requests")
            ->groupByRaw($period)
            ->get()
            ->keyBy('period');
    }

    /**
     * @return array{label: string, tick: ?string, visits: int, requests: int}
     */
    private function point(string $label, ?string $tick, ?object $totals): array
    {
        return [
            'label' => $label,
            'tick' => $tick,
            'visits' => (int) ($totals->visitors ?? 0),
            'requests' => (int) ($totals->requests ?? 0),
        ];
    }

    /**
     * @param  list<array{label: string, tick: ?string, visits: int, requests: int}>  $points
     * @return array{label: string, period: string, total: int, requests: int, points: list<array{label: string, tick: ?string, visits: int, requests: int}>}
     */
    private function range(string $label, CarbonInterface $from, array $points): array
    {
        $totals = SiteVisit::query()
            ->where('visited_at', '>=', $from)
            ->toBase()
            ->selectRaw('count(distinct visitor_hash) as visitors, sum(requests) as requests')
            ->first();

        return [
            'label' => $label,
            'period' => $from->isToday() ? $from->format('j F') : $from->format('j F').' — '.today()->format('j F'),
            'total' => (int) ($totals->visitors ?? 0),
            'requests' => (int) ($totals->requests ?? 0),
            'points' => $points,
        ];
    }
}
