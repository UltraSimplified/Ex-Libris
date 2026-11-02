<?php

namespace App\Nova\Cards;

use App\Models\SiteVisit;
use Carbon\CarbonInterface;
use Laravel\Nova\Card;

/**
 * Unique visitors to the site over the last 24 hours, 7 days and 30 days.
 * All three go to the page at once, so switching between them is instant.
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
     * @return array{label: string, period: string, total: int, points: list<array{label: string, tick: ?string, visits: int}>}
     */
    private function lastDayByHour(): array
    {
        $from = now()->startOfHour()->subHours(23);

        $visits = SiteVisit::query()
            ->where('visited_at', '>=', $from)
            ->toBase()
            ->selectRaw('visited_at as hour, count(*) as total')
            ->groupBy('visited_at')
            ->pluck('total', 'hour');

        $points = collect(range(0, 23))
            ->map(function (int $hoursOn) use ($from, $visits): array {
                $hour = $from->copy()->addHours($hoursOn);

                return [
                    'label' => $hour->format('D j M, g A'),
                    'tick' => $hour->hour % 4 === 0 ? $hour->format('g A') : null,
                    'visits' => (int) ($visits[$hour->toDateTimeString()] ?? 0),
                ];
            })
            ->all();

        return $this->range('24 Hours', $from, $points);
    }

    /**
     * Whole days up to today, oldest first, counting a visitor once a day
     * however many hours they came in. The axis marks count back from today.
     *
     * @return array{label: string, period: string, total: int, points: list<array{label: string, tick: ?string, visits: int}>}
     */
    private function lastDays(string $label, int $days, int $daysBetweenTicks, string $tickFormat): array
    {
        $from = today()->subDays($days - 1);

        $visits = SiteVisit::query()
            ->where('visited_at', '>=', $from)
            ->toBase()
            ->selectRaw('date(visited_at) as day, count(distinct visitor_hash) as total')
            ->groupByRaw('date(visited_at)')
            ->pluck('total', 'day');

        $points = collect(range($days - 1, 0))
            ->map(function (int $daysAgo) use ($daysBetweenTicks, $tickFormat, $visits): array {
                $day = today()->subDays($daysAgo);

                return [
                    'label' => $day->format('D j M'),
                    'tick' => $daysAgo % $daysBetweenTicks === 0 ? $day->format($tickFormat) : null,
                    'visits' => (int) ($visits[$day->toDateString()] ?? 0),
                ];
            })
            ->all();

        return $this->range($label, $from, $points);
    }

    /**
     * @param  list<array{label: string, tick: ?string, visits: int}>  $points
     * @return array{label: string, period: string, total: int, points: list<array{label: string, tick: ?string, visits: int}>}
     */
    private function range(string $label, CarbonInterface $from, array $points): array
    {
        return [
            'label' => $label,
            'period' => $from->isToday() ? $from->format('j F') : $from->format('j F').' — '.today()->format('j F'),
            'total' => SiteVisit::query()->where('visited_at', '>=', $from)->distinct()->count('visitor_hash'),
            'points' => $points,
        ];
    }
}
