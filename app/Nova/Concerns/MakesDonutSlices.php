<?php

namespace App\Nova\Concerns;

/**
 * Colours the slices of an Office donut chart, so every donut on the
 * dashboard draws from the same palette in the same order.
 */
trait MakesDonutSlices
{
    /**
     * Slice colours, in the order the slices come out.
     *
     * @var array<int, string>
     */
    private const DONUT_COLOURS = ['#F5573B', '#F0A23B', '#F5D93B', '#3BA5F5', '#8B5CF6', '#26C6A2'];

    /**
     * Gives each slice its colour, in the order the slices come, starting
     * the palette again past its end.
     *
     * @param  iterable<int, array{label: string, value: int}>  $slices
     * @return array<int, array{label: string, value: int, colour: string}>
     */
    protected function donutSlices(iterable $slices): array
    {
        return collect($slices)
            ->values()
            ->map(fn (array $slice, int $index): array => $slice + [
                'colour' => self::DONUT_COLOURS[$index % count(self::DONUT_COLOURS)],
            ])
            ->all();
    }
}
