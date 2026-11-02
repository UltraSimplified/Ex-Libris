<?php

namespace App\Nova\Cards;

use App\Models\SiteVisit;
use Laravel\Nova\Card;

/**
 * Unique visits to the site by day as a sparkline, ending on how many
 * different visitors came across the whole week.
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
            'heading' => 'Unique visits',
            'label' => 'Last '.SiteVisit::DAYS.' days',
            'days' => $this->days(),
            'total' => SiteVisit::query()
                ->where('visited_on', '>=', SiteVisit::firstReportedDay())
                ->distinct()
                ->count('visitor_hash'),
        ]);
    }

    /**
     * Every day in the window, oldest first, with quiet days as zero so the
     * line keeps its shape.
     *
     * @return list<array{day: string, visits: int}>
     */
    private function days(): array
    {
        $visits = SiteVisit::query()
            ->where('visited_on', '>=', SiteVisit::firstReportedDay())
            ->toBase()
            ->selectRaw('visited_on, count(*) as total')
            ->groupBy('visited_on')
            ->pluck('total', 'visited_on');

        return collect(range(SiteVisit::DAYS - 1, 0))
            ->map(function (int $daysAgo) use ($visits): array {
                $day = today()->subDays($daysAgo);

                return [
                    'day' => $day->format('D j M'),
                    'visits' => (int) ($visits[$day->toDateString()] ?? 0),
                ];
            })
            ->all();
    }
}
