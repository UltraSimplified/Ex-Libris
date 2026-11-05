<?php

namespace App\Nova\Cards;

use App\Models\FeatureRead;
use App\Models\Tag;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Card;

/**
 * What people spent their time reading over the last month: the time
 * divided across the magazine's categories, and the features that held
 * readers longest. Only what was read in the period appears, so the card
 * stays the same size however many features the archive holds.
 *
 * Features carry a tag rather than a category, which is what the front end
 * and the Nova resource both call the category, so the tag is what is
 * grouped on here.
 */
class FeaturesByCategory extends Card
{
    public $width = '1/2';

    /**
     * The days the card covers, today included.
     */
    public const DAYS = 30;

    /**
     * How many features the list runs to.
     */
    public const LEADERS = 5;

    /**
     * Slice colours, in the order the categories come out.
     *
     * @var array<int, string>
     */
    private const COLOURS = ['#F5573B', '#F0A23B', '#F5D93B', '#3BA5F5', '#8B5CF6', '#26C6A2'];

    public function component(): string
    {
        return 'gop-features-by-category';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $from = today()->subDays(self::DAYS - 1);
        $slices = $this->slices($from);

        return array_merge(parent::jsonSerialize(), [
            'heading' => 'Features',
            'period' => 'read in the last '.self::DAYS.' days',
            'total' => array_sum(array_column($slices, 'value')),
            'slices' => $slices,
            'listHeading' => 'Most read',
            'leaders' => $this->leaders($from),
            'emptyText' => 'No feature has been read in the last '.self::DAYS.' days.',
            'link' => '/resources/features',
            'linkText' => 'View all Features',
        ]);
    }

    /**
     * The seconds spent reading each category, longest first.
     *
     * @return array<int, array{label: string, value: int, colour: string}>
     */
    private function slices(CarbonInterface $from): array
    {
        $tags = Tag::pluck('tag', 'id');

        return $this->readingSince($from)
            ->join('features', 'features.id', '=', 'feature_reads.feature_id')
            ->selectRaw('features.tag_id, sum(feature_reads.seconds_read) as seconds')
            ->groupBy('features.tag_id')
            ->orderByDesc('seconds')
            ->toBase()
            ->get()
            ->values()
            ->map(fn (object $row, int $index): array => [
                'label' => $tags[$row->tag_id] ?? 'Uncategorised',
                'value' => (int) $row->seconds,
                'colour' => self::COLOURS[$index % count(self::COLOURS)],
            ])
            ->all();
    }

    /**
     * The features read longest, with how many began them, the average
     * time a read lasted, and the share of readers who reached the end.
     *
     * @return array<int, array{name: string, seconds: int, reads: int, averageRead: ?int, reachedEnd: ?int, link: string}>
     */
    private function leaders(CarbonInterface $from): array
    {
        return $this->readingSince($from)
            ->selectRaw('feature_id, sum(seconds_read) as seconds, sum(read_count) as read_total, sum(reached_end_count) as reached_end_total')
            ->groupBy('feature_id')
            ->orderByDesc('seconds')
            ->limit(self::LEADERS)
            ->with('feature:id,title')
            ->get()
            ->map(fn (FeatureRead $row): array => $this->leader($row))
            ->all();
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
    private function readingSince(CarbonInterface $from): Builder
    {
        return FeatureRead::query()->where('feature_reads.read_on', '>=', $from->toDateString());
    }
}
