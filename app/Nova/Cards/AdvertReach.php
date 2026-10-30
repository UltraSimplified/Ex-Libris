<?php

namespace App\Nova\Cards;

use App\Models\Advert;
use App\Models\AdvertEvent;
use App\Services\Adverts\AdvertReport;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Nova\Card;

/**
 * The advert figures, one live advert at a time over its creative, which
 * the card turns through as a carousel. With nothing live it falls back on
 * the figures for every advert together.
 */
class AdvertReach extends Card
{
    public $width = '1/2';

    /**
     * The window the figures cover.
     */
    private const DAYS = 30;

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

        $impressions = AdvertEvent::query()->impressions()->where('created_at', '>=', $since)->count();
        $clicks = AdvertEvent::query()->clicks()->where('created_at', '>=', $since)->count();

        return array_merge(parent::jsonSerialize(), [
            'heading' => 'Adverts',
            'adverts' => $this->live($since),
            'impressions' => $impressions,
            'summary' => $this->summary($impressions, $clicks),
            'link' => '/resources/adverts',
            'linkText' => 'View all adverts',
        ]);
    }

    /**
     * Every advert on air, the most recently started first, each with its
     * own figures over the window and a signed link to its full-run report.
     *
     * @return list<array{title: string, slot: string, image: string|null, link: string, impressions: int, summary: string, report: string, reportFilename: string}>
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

        return $adverts->map(function (Advert $advert) use ($counts, $report): array {
            $totals = ($counts->get($advert->id) ?? collect())->pluck('total', 'type');
            $impressions = (int) ($totals['impression'] ?? 0);
            $clicks = (int) ($totals['click'] ?? 0);

            return [
                'title' => (string) $advert->title,
                'slot' => (string) $advert->slot,
                'image' => filled($advert->desktop_image)
                    ? Storage::disk('do')->url($advert->desktop_image)
                    : null,
                'link' => '/resources/adverts/'.$advert->id,
                'impressions' => $impressions,
                'summary' => $this->summary($impressions, $clicks),
                'report' => $report->downloadUrl($advert),
                'reportFilename' => $report->filename($advert),
            ];
        })->values()->all();
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
