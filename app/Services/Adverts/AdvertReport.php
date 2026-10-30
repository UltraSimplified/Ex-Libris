<?php

namespace App\Services\Adverts;

use App\Models\Advert;
use App\Models\AdvertEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * The figures for one advert across its whole run, for the PDF report the
 * Office hands to whoever booked it.
 *
 * The run is the advert's dates, from its first day to its last, or to
 * today while it is still running. An advert with no start date is taken
 * from its first impression; one with no end date runs to today.
 */
final class AdvertReport
{
    /**
     * A run longer than this is charted a week at a time rather than a day.
     */
    private const DAILY_UP_TO_DAYS = 42;

    private const TOP_PLACES = 8;

    private const TOP_PAGES = 10;

    /**
     * Points in a millimetre, the unit the PDF canvas works in.
     */
    private const MM = 72 / 25.4;

    /**
     * Everything the report shows.
     *
     * @return array<string, mixed>
     */
    public function for(Advert $advert): array
    {
        $today = CarbonImmutable::today();
        $start = $this->startOf($advert, $today);
        $end = $advert->run_to ? CarbonImmutable::parse($advert->run_to)->startOfDay() : $today;
        $until = $end->min($today);
        $started = $start->lte($today);

        $events = AdvertEvent::query()
            ->where('advert_id', $advert->id)
            ->where('created_at', '>=', $start->startOfDay())
            ->where('created_at', '<=', $until->endOfDay());

        $impressions = (clone $events)->impressions()->count();
        $clicks = (clone $events)->clicks()->count();
        $days = $started ? (int) $start->diffInDays($until) + 1 : 0;
        $series = $started ? $this->series($events, $start, $until) : ['period' => 'day', 'rows' => []];

        return [
            'advert' => [
                'title' => (string) $advert->title,
                'campaign' => $advert->campaign,
                'owner' => $advert->owner,
                'slot' => $advert->slot,
                'link' => $advert->link,
                'creative' => $this->creative($advert),
            ],
            'run' => [
                'from' => $start,
                'to' => $end,
                'until' => $until,
                'started' => $started,
                'running' => $started && $end->gt($today),
                'days' => $days,
            ],
            'totals' => [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'rate' => $this->rate($impressions, $clicks),
                'perDay' => $days > 0 ? round($impressions / $days, 1) : 0,
                'best' => collect($series['rows'] ?? [])->sortByDesc('impressions')->first(),
            ],
            'series' => $series,
            'devices' => $this->split($events, 'variant', ['desktop' => 'Desktop', 'mobile' => 'Mobile']),
            'placements' => $this->split($events, 'placement', ['panel' => 'Sponsorship panel', 'list' => 'In-list', '' => 'Not recorded']),
            'places' => $this->places($events),
            'pages' => $this->pages($events, $impressions),
            'generatedAt' => CarbonImmutable::now(),
        ];
    }

    /**
     * The report as a PDF, laid out and numbered.
     *
     * The page count is only known once the pages are laid out, so the
     * numbers are stamped on afterwards rather than set in the page itself.
     */
    public function pdf(Advert $advert): DomPdf
    {
        $pdf = Pdf::loadView('reports.advert', $this->for($advert))->setPaper('a4');
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('helvetica');
        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $size = 7.5;
        $width = $dompdf->getFontMetrics()->getTextWidth('Page 00 of 00', $font, $size);

        // In line with the footer the page draws: 16mm in from the right edge, 8mm up.
        $canvas->page_text(
            $canvas->get_width() - self::MM * 16 - $width,
            $canvas->get_height() - self::MM * 8 - 9.5,
            $text,
            $font,
            $size,
            [0.392, 0.455, 0.545],
        );

        return $pdf;
    }

    /**
     * A signed link to download the report, good for two hours, as the
     * listings export's is.
     */
    public function downloadUrl(Advert $advert): string
    {
        return URL::temporarySignedRoute('exports.advert-report', now()->addHours(2), ['advert' => $advert->id]);
    }

    /**
     * The file the report downloads as.
     */
    public function filename(Advert $advert): string
    {
        return 'good-on-paper-advert-report-'
            .(Str::slug((string) $advert->title) ?: 'advert-'.$advert->id)
            .'-'.CarbonImmutable::today()->toDateString().'.pdf';
    }

    private function startOf(Advert $advert, CarbonImmutable $today): CarbonImmutable
    {
        if ($advert->run_from) {
            return CarbonImmutable::parse($advert->run_from)->startOfDay();
        }

        $first = AdvertEvent::query()->where('advert_id', $advert->id)->min('created_at');

        return $first ? CarbonImmutable::parse($first)->startOfDay() : $today;
    }

    /**
     * Impressions and clicks a day at a time, or a week at a time for a long
     * run, with each period's bar as a share of the busiest.
     *
     * @param  Builder<AdvertEvent>  $events
     * @return array{period: string, rows: list<array{label: string, impressions: int, clicks: int, rate: float, bar: float}>}
     */
    private function series(Builder $events, CarbonImmutable $start, CarbonImmutable $until): array
    {
        $counts = (clone $events)
            ->whereIn('type', ['impression', 'click'])
            ->selectRaw('DATE(created_at) as day, type, count(*) as total')
            ->groupBy('day', 'type')
            ->get()
            ->groupBy('day');

        $weekly = $start->diffInDays($until) + 1 > self::DAILY_UP_TO_DAYS;
        $rows = [];

        for ($day = $start; $day->lte($until); $day = $day->addDay()) {
            $periodStart = $weekly ? $day->startOfWeek(CarbonInterface::MONDAY)->max($start) : $day;
            $key = $periodStart->toDateString();
            $totals = ($counts->get($day->toDateString()) ?? collect())->pluck('total', 'type');

            $rows[$key] ??= [
                'label' => $weekly
                    ? 'w/c '.$periodStart->format('j M')
                    : $day->format('D j M'),
                'impressions' => 0,
                'clicks' => 0,
            ];
            $rows[$key]['impressions'] += (int) ($totals['impression'] ?? 0);
            $rows[$key]['clicks'] += (int) ($totals['click'] ?? 0);
        }

        $busiest = max(1, ...array_column($rows, 'impressions'));

        return [
            'period' => $weekly ? 'week' : 'day',
            'rows' => array_values(array_map(fn (array $row): array => [
                ...$row,
                'rate' => $this->rate($row['impressions'], $row['clicks']),
                'bar' => round($row['impressions'] / $busiest * 100, 1),
            ], $rows)),
        ];
    }

    /**
     * Impressions and clicks split by one column, in the order given, with
     * anything else under its own name and nothing recorded as "Unknown".
     *
     * @param  Builder<AdvertEvent>  $events
     * @param  array<string, string>  $names
     * @return list<array{label: string, impressions: int, clicks: int, rate: float, share: float}>
     */
    private function split(Builder $events, string $column, array $names = []): array
    {
        $grouped = $this->countsBy($events, $column);
        $impressions = max(1, $grouped->sum('impressions'));

        $ordered = collect($names)->keys()
            ->filter(fn (string $key): bool => $grouped->has($key))
            ->merge($grouped->keys()->diff(array_keys($names)))
            ->values();

        return $ordered->map(fn ($key): array => [
            'label' => $names[$key] ?? ($key === '' ? 'Unknown' : Str::headline((string) $key)),
            'impressions' => $grouped[$key]['impressions'],
            'clicks' => $grouped[$key]['clicks'],
            'rate' => $this->rate($grouped[$key]['impressions'], $grouped[$key]['clicks']),
            'share' => round($grouped[$key]['impressions'] / $impressions * 100, 1),
        ])->all();
    }

    /**
     * Where the advert was seen, busiest first, the long tail gathered up.
     *
     * @param  Builder<AdvertEvent>  $events
     * @return list<array{label: string, impressions: int, clicks: int, rate: float, share: float}>
     */
    private function places(Builder $events): array
    {
        $places = collect($this->split($events, 'locality_bucket'))->sortByDesc('impressions')->values();

        if ($places->count() <= self::TOP_PLACES) {
            return $places->all();
        }

        $rest = $places->slice(self::TOP_PLACES - 1);

        return $places->take(self::TOP_PLACES - 1)->push([
            'label' => 'Everywhere else',
            'impressions' => $rest->sum('impressions'),
            'clicks' => $rest->sum('clicks'),
            'rate' => $this->rate($rest->sum('impressions'), $rest->sum('clicks')),
            'share' => round($rest->sum('share'), 1),
        ])->all();
    }

    /**
     * The pages the advert was seen on most.
     *
     * @param  Builder<AdvertEvent>  $events
     * @return list<array{page: string, impressions: int, share: float}>
     */
    private function pages(Builder $events, int $impressions): array
    {
        return (clone $events)
            ->impressions()
            ->whereNotNull('page')
            ->selectRaw('page, count(*) as total')
            ->groupBy('page')
            ->orderByDesc('total')
            ->limit(self::TOP_PAGES)
            ->get()
            ->map(fn ($row): array => [
                'page' => (string) $row->page,
                'impressions' => (int) $row->total,
                'share' => round($row->total / max(1, $impressions) * 100, 1),
            ])
            ->all();
    }

    /**
     * Impressions and clicks for each value of a column.
     *
     * @param  Builder<AdvertEvent>  $events
     * @return Collection<string, array{impressions: int, clicks: int}>
     */
    private function countsBy(Builder $events, string $column): Collection
    {
        return (clone $events)
            ->whereIn('type', ['impression', 'click'])
            ->selectRaw("{$column} as value, type, count(*) as total")
            ->groupBy('value', 'type')
            ->get()
            ->groupBy(fn ($row): string => (string) $row->value)
            ->map(fn (Collection $rows): array => [
                'impressions' => (int) $rows->firstWhere('type', 'impression')?->total,
                'clicks' => (int) $rows->firstWhere('type', 'click')?->total,
            ]);
    }

    private function rate(int $impressions, int $clicks): float
    {
        return $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0;
    }

    /**
     * The creative as an image the PDF can carry, or null if it cannot be
     * read: the mobile version, which is square and sits beside the figures,
     * or the desktop one when there is no mobile one. It is fetched here and
     * embedded, so the PDF renderer never has to reach the Spaces disk.
     */
    private function creative(Advert $advert): ?string
    {
        $path = $advert->mobile_image ?: $advert->desktop_image;

        if (blank($path)) {
            return null;
        }

        try {
            $jpeg = Image::decodeBinary(Storage::disk('do')->get($path))->scaleDown(1200, 1200)->encodeUsingFormat(Format::JPEG, quality: 85);

            return 'data:image/jpeg;base64,'.base64_encode((string) $jpeg);
        } catch (Throwable) {
            return null;
        }
    }
}
