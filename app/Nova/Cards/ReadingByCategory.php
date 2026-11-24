<?php

namespace App\Nova\Cards;

/**
 * The time spent reading over the last month, divided across the
 * categories: the top half of the dashboard's reading card, on the
 * features index as a card of its own.
 */
class ReadingByCategory extends FeatureReading
{
    public function component(): string
    {
        return 'gop-reading-by-category';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), $this->byCategory(), [
            'heading' => 'Reading by category',
            'emptyText' => $this->emptyText(),
        ]);
    }
}
