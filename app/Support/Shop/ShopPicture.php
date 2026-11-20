<?php

namespace App\Support\Shop;

use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * The one shape every picture in the shop is cut to: A-paper portrait, the
 * shape of the prints, at most 1131 by 1600. Category images, product
 * covers and a product's other pictures all share it, so the cards on the
 * shop pages line up and a product's page keeps its height as its
 * pictures are clicked through.
 *
 * Kept in step with the aspect-[1131/1600] the shop pages frame them in,
 * which also lines up pictures uploaded before there was a standard.
 */
final class ShopPicture
{
    public const WIDTH = 1131;

    public const HEIGHT = 1600;

    /**
     * An Office upload field that crops to the shape and scales down to
     * the size, saved as webp. A smaller picture keeps the shape but is
     * not enlarged.
     */
    public static function field(string $name, ?string $attribute = null): ImageCropper
    {
        return ImageCropper::make($name, $attribute)
            ->croppable(self::WIDTH / self::HEIGHT)
            ->resize(self::WIDTH, self::HEIGHT)
            ->convert('webp');
    }
}
