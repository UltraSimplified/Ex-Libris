<?php

namespace App\Http\Requests\Concerns;

use App\Support\Brand;

/**
 * The brand colour fields, shared by every form that sets them.
 */
trait ValidatesBrandColours
{
    /**
     * A colour input always posts something, and an empty one posts the
     * empty string, which the hex rule would reject on a form the owner
     * never touched. Empty means unset.
     */
    protected function nullEmptyBrandColours(): void
    {
        $this->merge(
            collect(Brand::COLUMNS)
                ->filter(fn (string $field): bool => $this->input($field) === '')
                ->mapWithKeys(fn (string $field): array => [$field => null])
                ->all()
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function brandColourRules(): array
    {
        return collect(Brand::COLUMNS)
            ->mapWithKeys(fn (string $field): array => [$field => ['nullable', 'hex_color']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function brandColourAttributes(): array
    {
        return [
            'brand_colour' => 'brand colour',
            'accent_colour1' => 'accent colour',
            'accent_colour2' => 'second accent colour',
            'brand_background_colour' => 'background colour',
        ];
    }
}
