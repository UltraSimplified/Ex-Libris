<?php

namespace App\Nova\Cards;

use App\Models\Feature;
use App\Models\Tag;
use Laravel\Nova\Card;

/**
 * How the published features divide across the magazine's categories.
 *
 * Features carry a tag rather than a category, which is what the front end
 * and the Nova resource both call the category, so the tag is what is
 * counted here.
 */
class FeaturesByCategory extends Card
{
    public $width = '1/2';

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
        $slices = $this->slices();

        return array_merge(parent::jsonSerialize(), [
            'heading' => 'Features',
            'total' => array_sum(array_column($slices, 'value')),
            'slices' => $slices,
            'emptyText' => 'No features have been published yet.',
            'link' => '/resources/features',
            'linkText' => 'View all Features',
        ]);
    }

    /**
     * @return array<int, array{label: string, value: int, colour: string}>
     */
    private function slices(): array
    {
        $tags = Tag::pluck('tag', 'id');

        return Feature::query()
            ->selectRaw('tag_id, count(*) as total')
            ->groupBy('tag_id')
            ->orderByDesc('total')
            ->get()
            ->values()
            ->map(fn ($row, int $index): array => [
                'label' => $tags[$row->tag_id] ?? 'Uncategorised',
                'value' => (int) $row->total,
                'colour' => self::COLOURS[$index % count(self::COLOURS)],
            ])
            ->all();
    }
}
