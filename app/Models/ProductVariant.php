<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The thing actually bought: a product in one colour and size, with its
 * own price in pence and its own stock.
 *
 * A print or a record has one variant with neither colour nor size. Stock
 * left empty means there is no limit; nought means sold out. A sale price
 * below the price puts it on sale, and is what is charged.
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Clothing sizes smallest first, the order they are offered in. A size
     * not in the list sorts after them all.
     *
     * @var list<string>
     */
    public const SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];

    /**
     * What a variant with neither a colour nor a size is called.
     */
    public const STANDARD = 'Standard';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'colour',
        'colour_hex',
        'size',
        'sku',
        'price',
        'sale_price',
        'stock',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'sale_price' => 'integer',
            'stock' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * The product this is a colour and size of.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * How the variant is named on the page and the order: "Black / M",
     * "Black", "M", or Standard when it has neither.
     */
    public function displayName(): string
    {
        $parts = array_filter([$this->colour, $this->size], 'filled');

        return $parts === [] ? self::STANDARD : implode(' / ', $parts);
    }

    /**
     * Whether it is on sale: it has a sale price, and that is below its
     * price. A sale price at or above the price is ignored.
     */
    public function isOnSale(): bool
    {
        return $this->sale_price !== null && $this->sale_price < $this->price;
    }

    /**
     * What it costs now, in pence: the sale price while on sale, otherwise
     * the price. Everything that charges uses this.
     */
    public function currentPrice(): int
    {
        return $this->isOnSale() ? $this->sale_price : $this->price;
    }

    /**
     * The usual price to show struck through beside the sale price, or
     * none when it is not on sale.
     */
    public function struckPrice(): ?int
    {
        return $this->isOnSale() ? $this->price : null;
    }

    /**
     * Whether any are left to sell.
     */
    public function isInStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }

    /**
     * Where a size falls in the run from smallest to largest.
     */
    public static function sizeRank(?string $size): int
    {
        $rank = array_search(strtoupper((string) $size), self::SIZES, true);

        return $rank === false ? count(self::SIZES) : $rank;
    }
}
