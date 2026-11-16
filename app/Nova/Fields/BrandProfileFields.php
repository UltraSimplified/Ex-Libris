<?php

namespace App\Nova\Fields;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Outl1ne\NovaColorField\Color;
use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * What a promoter or a festival is known by, as the Office edits it: how
 * to reach it, its logo and banner, what it says about itself and its
 * four colours.
 *
 * The pictures come out the same size as the dashboard's (BrandImage), so
 * either can replace the other's.
 */
final class BrandProfileFields
{
    /**
     * @return array<int, Field>
     *
     * @throws \Exception
     */
    public function fields(): array
    {
        return [
            Text::make('website')->nullable()->hideFromIndex(),
            Text::make('email')->nullable()->hideFromIndex(),
            Text::make('phone')->hideFromIndex(),
            $this->picture('Logo')
                ->croppable(512 / 512)
                ->resize(512, 512),
            $this->picture('Banner')
                ->croppable()
                ->resize(1280)
                ->hideFromIndex(),
            Textarea::make('about')
                ->nullable()
                ->rules('nullable', 'string', 'max:2000')
                ->hideFromIndex(),
            Color::make('Brand Colour', 'brand_colour'),
            Color::make('Accent colour', 'accent_colour1'),
            Color::make('Accent colour #2', 'accent_colour2')->hideFromIndex(),
            Color::make('Brand Background Colour', 'brand_background_colour')->hideFromIndex(),
        ];
    }

    private function picture(string $name): ImageCropper
    {
        return ImageCropper::make($name)
            ->convert('webp')
            ->disk('do')
            ->nullable()
            ->indexWidth(100)
            ->detailWidth(20)
            ->maxWidth(100)
            ->textAlign('center')
            ->prunable();
    }
}
