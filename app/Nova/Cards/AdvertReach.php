<?php

namespace App\Nova\Cards;

use App\Models\Advert;
use App\Models\AdvertEvent;
use App\Services\Adverts\AdvertPool;
use App\Services\Adverts\AdvertReport;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Nova\Card;

/**
 * The advert figures in two sections, one for the in-list slots and one for
 * the sponsorship panel. The in-list section turns through its live adverts
 * as a carousel, one at a time over its creative; the panel has one sponsor
 * at a time, so its section shows just the one. With nothing live, a section
 * falls back on the figures for every advert in its slot together.
 */
class AdvertReach extends Card
{
    public $width = '1/2';

    /**
     * The window the figures cover.
     */
    private const DAYS = 30;

    /**
     * The slots in the order the card shows them.
     */
    private const POOLS = [AdvertPool::InList, AdvertPool::Sponsorship];

    public function component(): string
    {
        return 'gop-advert-reach';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $since = now()->subDays(self::DAYS);
        $live = $this->live($since);
        $totals = $this->totals($since);

        return array_merge(parent::jsonSerialize(), [
            'heading' => 'Adverts',
            'sections' => array_map(
                fn (AdvertPool $pool): array => $this->section($pool, $live[$pool->value] ?? [], $totals[$pool->value] ?? []),
                self::POOLS,
            ),
            'link' => '/resources/adverts',
            'linkText' => 'View all adverts',
        ]);
    }

    /**
     * One slot's section: its live adverts, and the figures for every advert
     * in the slot together, which stand in while none is live.
     *
     * The panel is meant to have one sponsor at a time, so its section shows
     * only the one most recently started. Should another be live as well,
     * the two take turns in the panel, and the section says so.
     *
     * @param  list<array{title: string, image: string|null, link: string, impressions: int, summary: string, report: string, reportFilename: string}>  $adverts
     * @param  array<string, int>  $totals  Events over the window, by type.
     * @return array{heading: string, emptyText: string, notice: string|null, adverts: list<array<string, mixed>>, impressions: int, summary: string}
     */
    private function section(AdvertPool $pool, array $adverts, array $totals): array
    {
        $impressions = $totals['impression'] ?? 0;
        $sharesTheSlot = $pool === AdvertPool::Sponsorship && count($adverts) > 1;

        [$heading, $emptyText] = match ($pool) {
            AdvertPool::InList => ['In-list', 'No in-list advert is running at the moment.'],
            AdvertPool::Sponsorship => ['Sponsorship panel', 'No sponsorship is running, so the panel shows Good on Paper’s own advert.'],
        };

        return [
            'heading' => $heading,
            'emptyText' => $emptyText,
            'notice' => $sharesTheSlot ? count($adverts).' sponsorships are live and taking turns in the panel.' : null,
            'adverts' => $pool === AdvertPool::Sponsorship ? array_slice($adverts, 0, 1) : $adverts,
            'impressions' => $impressions,
            'summary' => $this->summary($impressions, $totals['click'] ?? 0),
        ];
    }

    /**
     * Every advert on air, the most recently started first, each with its
     * own figures over the window and a signed link to its full-run report,
     * grouped by the slot it fills.
     *
     * @return array<string, list<array{title: string, image: string|null, link: string, impressions: int, summary: string, report: string, reportFilename: string}>>
     */
    private function live(CarbonInterface $since): array
    {
        $adverts = Advert::query()->live()->latest('run_from')->latest('id')->get();

        $counts = AdvertEvent::query()
            ->whereIn('advert_id', $adverts->modelKeys())
            ->whereIn('type', ['impression', 'click'])
            ->where('created_at', '>=', $since)
            ->selectRaw('advert_id, type, count(*) as total')
            ->groupBy('advert_id', 'type')
            ->get()
            ->groupBy('advert_id');

        $report = app(AdvertReport::class);

        return $adverts
            ->groupBy(fn (Advert $advert): string => self::poolOf((bool) $advert->sponsorship)->value)
            ->map(fn (Collection $pool): array => $pool->map(function (Advert $advert) use ($counts, $report): array {
                $totals = ($counts->get($advert->id) ?? collect())->pluck('total', 'type');
                $impressions = (int) ($totals['impression'] ?? 0);

                return [
                    'title' => (string) $advert->title,
                    'image' => filled($advert->desktop_image)
                        ? Storage::disk('do')->url($advert->desktop_image)
                        : null,
                    'link' => '/resources/adverts/'.$advert->id,
                    'impressions' => $impressions,
                    'summary' => $this->summary($impressions, (int) ($totals['click'] ?? 0)),
                    'report' => $report->downloadUrl($advert),
                    'reportFilename' => $report->filename($advert),
                ];
            })->values()->all())
            ->all();
    }

    /**
     * Impressions and clicks over the window for every advert in each slot,
     * archived ones included, keyed by slot and then by type.
     *
     * @return array<string, array<string, int>>
     */
    private function totals(CarbonInterface $since): array
    {
        return AdvertEvent::query()
            ->join('adverts', 'adverts.id', '=', 'advert_events.advert_id')
            ->whereIn('advert_events.type', ['impression', 'click'])
            ->where('advert_events.created_at', '>=', $since)
            ->selectRaw('adverts.sponsorship, advert_events.type, count(*) as total')
            ->groupBy('adverts.sponsorship', 'advert_events.type')
            ->get()
            ->groupBy(fn (AdvertEvent $row): string => self::poolOf((bool) $row->getAttribute('sponsorship'))->value)
            ->map(fn (Collection $rows): array => $rows->mapWithKeys(
                fn (AdvertEvent $row): array => [(string) $row->type => (int) $row->getAttribute('total')]
            )->all())
            ->all();
    }

    /**
     * The slot an advert fills.
     */
    private static function poolOf(bool $sponsorship): AdvertPool
    {
        return $sponsorship ? AdvertPool::Sponsorship : AdvertPool::InList;
    }

    /**
     * Clicks and the rate they represent, in one line.
     */
    private function summary(int $impressions, int $clicks): string
    {
        $rate = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

        return sprintf(
            'Last %d days · %s %s · %s%% click-through',
            self::DAYS,
            number_format($clicks),
            Str::plural('click', $clicks),
            $rate
        );
    }
}
