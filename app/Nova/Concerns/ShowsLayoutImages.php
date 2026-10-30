<?php

namespace App\Nova\Concerns;

use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Image;

trait ShowsLayoutImages
{
    /**
     * An image field inside one of a Flexible field's layouts.
     *
     * Flexible copies a layout's fields for every layout it holds, but
     * leaves an image field's thumbnail and preview bound to the empty field
     * it copied, so a saved image came back as its address rather than a
     * picture. These read the value Nova hands them instead.
     *
     * The field's own delete button cannot reach a field inside a layout, so
     * it is hidden. An image is replaced by uploading another.
     */
    protected function layoutImage(Image $field, string $disk): Image
    {
        $url = static fn (mixed $value, ?string $fieldDisk): ?string => is_string($value) && $value !== ''
            ? Storage::disk($fieldDisk ?? $disk)->url($value)
            : null;

        return $field
            ->disk($disk)
            ->thumbnail($url)
            ->preview($url)
            ->deletable(false);
    }
}
