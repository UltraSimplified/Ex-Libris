<?php

namespace App\Services\Shop;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Every colour of a product in every size, made in one go rather than one
 * at a time - five colours in six sizes is thirty variants.
 */
class VariantGrid
{
    /**
     * Colours as the office types them, comma separated, each with an
     * optional swatch: "Black #111111, White #ffffff, Natural".
     *
     * @return list<array{name: string, hex: ?string}>
     */
    public static function parseColours(string $input): array
    {
        return collect(explode(',', $input))
            ->map(fn (string $colour): string => trim($colour))
            ->filter()
            ->map(function (string $colour): array {
                $hex = preg_match('/#[0-9a-f]{6}\b/i', $colour, $match) ? strtolower($match[0]) : null;
                $name = trim(preg_replace('/#[0-9a-f]{6}\b/i', '', $colour));

                return ['name' => $name, 'hex' => $hex];
            })
            ->filter(fn (array $colour): bool => $colour['name'] !== '')
            ->unique('name')
            ->values()
            ->all();
    }

    /**
     * Sizes as the office types them, comma separated, put smallest first.
     * A size that is one of the standard run is written the standard way,
     * so "xl" becomes "XL".
     *
     * @return list<string>
     */
    public static function parseSizes(string $input): array
    {
        return collect(explode(',', $input))
            ->map(fn (string $size): string => trim($size))
            ->filter()
            ->map(fn (string $size): string => in_array(strtoupper($size), ProductVariant::SIZES, true) ? strtoupper($size) : $size)
            ->unique()
            ->sortBy(fn (string $size): int => ProductVariant::sizeRank($size))
            ->values()
            ->all();
    }

    /**
     * Make the product's variants for each colour in each size, at the
     * given price in pence and stock, and sale price if they are on sale.
     *
     * A colour and size the product already has is left as it is. One that
     * was deleted is brought back at the new price and stock rather than
     * made again. Once a product has colours or sizes, its standard variant
     * - the one a print is sold as - no longer makes sense, so it is
     * removed.
     *
     * @param  list<array{name: string, hex: ?string}>  $colours
     * @param  list<string>  $sizes
     * @return array{made: int, kept: int, removedStandard: bool}
     */
    public function generate(Product $product, array $colours, array $sizes, int $price, ?int $stock, ?int $salePrice = null): array
    {
        $colours = $colours === [] ? [['name' => null, 'hex' => null]] : $colours;
        $sizes = $sizes === [] ? [null] : $sizes;

        return DB::transaction(function () use ($product, $colours, $sizes, $price, $stock, $salePrice): array {
            $made = 0;
            $kept = 0;

            foreach ($colours as $colourIndex => $colour) {
                foreach ($sizes as $size) {
                    $variant = $product->variants()->withTrashed()->firstOrNew([
                        'colour' => $colour['name'],
                        'size' => $size,
                    ]);

                    if ($variant->exists && ! $variant->trashed()) {
                        $kept++;

                        continue;
                    }

                    $variant->fill([
                        'colour_hex' => $colour['hex'],
                        'price' => $price,
                        'sale_price' => $salePrice,
                        'stock' => $stock,
                        'position' => $colourIndex * 10 + ProductVariant::sizeRank($size),
                    ]);
                    $variant->deleted_at = null;
                    $variant->save();

                    $made++;
                }
            }

            $removedStandard = $colours[0]['name'] !== null || $sizes[0] !== null
                ? $product->variants()->whereNull('colour')->whereNull('size')->delete() > 0
                : false;

            return ['made' => $made, 'kept' => $kept, 'removedStandard' => $removedStandard];
        });
    }
}
