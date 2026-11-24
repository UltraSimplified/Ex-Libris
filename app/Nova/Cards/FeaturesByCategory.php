<?php

namespace App\Nova\Cards;

/**
 * The dashboard's reading card: the time divided across the categories,
 * and the features the most people read beneath it.
 */
class FeaturesByCategory extends FeatureReading
{
    public $width = '1/2';

    public function component(): string
    {
        return 'gop-features-by-category';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), $this->byCategory(), [
            'heading' => 'Features',
            'listHeading' => 'Most read',
            'leaders' => $this->leaders(),
            'emptyText' => $this->emptyText(),
            'link' => '/resources/features',
            'linkText' => 'View all Features',
        ]);
    }
}
