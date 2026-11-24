<?php

namespace App\Nova\Cards;

/**
 * The features the most people read over the last month: the bottom half
 * of the dashboard's reading card, on the features index as a card of its
 * own.
 */
class MostReadFeatures extends FeatureReading
{
    public function component(): string
    {
        return 'gop-most-read';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'heading' => 'Most read',
            'period' => ucfirst($this->period()),
            'leaders' => $this->leaders(),
            'emptyText' => $this->emptyText(),
        ]);
    }
}
