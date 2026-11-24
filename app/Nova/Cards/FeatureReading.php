<?php

namespace App\Nova\Cards;

use App\Models\FeatureRead;
use App\Models\Tag;
use App\Nova\Concerns\MakesDonutSlices;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Card;

/**
 * What people spent their time reading over the last month: the time
 * divided across the magazine's categories, and the features the most
 * people read. Only what was read in the period appears, so the cards stay
 * the same size however many features the archive holds.
 *
 * The dashboard shows both in one card; the features index shows each in
 * a card of its own, beside the writers list.
 *
 * Features carry a tag rather than a category, which is what the front end
 * and the Nova resource both call the category, so the tag is what is
 * grouped on here.
 */
abstract class FeatureReading extends Card
{
    use MakesDonutSlices;

    /**
     * A third each on the features index, to sit in a row with the writers
     * list.
     */
    public $width = '1/3';

    /**
     * The days the cards cover, today included.
     */
    public const DAYS = 30;

    /**
     * How many features the most-read list runs to.
     */
    public const LEADERS = 5;

    /**
     * The period, as it reads after a figure.
     */
    protected function period(): string
    {
        return 'read in the last '.self::DAYS.' days';
    }

    protected function emptyText(): string
    {
        return 'No feature has been read in the last '.self::DAYS.' days.';
    }

    /**
     * The reading time, in all and by category, and the period it covers.
     *
     * @return array{period: string, total: int, slices: array<int, array{label: string, value: int, colour: string}>}
     */
    protected function byCategory(): array
    {
        $slices = $this->slices();

        return [
            'period' => $this->period(),
            'total' => array_sum(array_column($slices, 'value')),
            'slices' => $slices,
        ];
    }

    /**
     * The features the most people began, with the time they spent on
     * them, the average time a read lasted, and the share of readers who
     * reached the end. Of two read as often, the one read longer leads.
     *
     * @return array<int, array{name: string, seconds: int, reads: int, averageRead: ?int, reachedEnd: ?int, link: string}>
     */
    protected function leaders(): array
    {
        return $this->reading()
            ->selectRaw('feature_id, sum(seconds_read) as seconds, sum(read_count) as read_total, sum(reached_end_count) as reached_end_total')
            ->groupBy('feature_id')
            ->orderByDesc('read_total')
            ->orderByDesc('seconds')
            ->limit(self::LEADERS)
            ->with('feature:id,title')
            ->get()
            ->map(fn (FeatureRead $row): array => $this->leader($row))
            ->all();
    }

    /**
     * The seconds spent reading each category, longest first.
     *
     * @return array<int, array{label: string, value: int, colour: string}>
     */
    private function slices(): array
    {
        $tags = Tag::pluck('tag', 'id');

        return $this->donutSlices($this->reading()
            ->join('features', 'features.id', '=', 'feature_reads.feature_id')
            ->selectRaw('features.tag_id, sum(feature_reads.seconds_read) as seconds')
            ->groupBy('features.tag_id')
            ->orderByDesc('seconds')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'label' => $tags[$row->tag_id] ?? 'Uncategorised',
                'value' => (int) $row->seconds,
            ]));
    }

    /**
     * @return array{name: string, seconds: int, reads: int, averageRead: ?int, reachedEnd: ?int, link: string}
     */
    private function leader(FeatureRead $row): array
    {
        $reads = (int) $row->getAttribute('read_total');
        $seconds = (int) $row->getAttribute('seconds');

        return [
            'name' => (string) $row->feature?->title,
            'seconds' => $seconds,
            'reads' => $reads,
            'averageRead' => $reads > 0 ? (int) round($seconds / $reads) : null,
            'reachedEnd' => $reads > 0 ? min(100, (int) round((int) $row->getAttribute('reached_end_total') / $reads * 100)) : null,
            'link' => '/resources/features/'.$row->feature_id,
        ];
    }

    /**
     * @return Builder<FeatureRead>
     */
    private function reading(): Builder
    {
        return FeatureRead::query()->where('feature_reads.read_on', '>=', today()->subDays(self::DAYS - 1)->toDateString());
    }
}
