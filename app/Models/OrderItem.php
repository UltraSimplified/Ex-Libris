<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order, with the name, price and postage it was bought at
 * copied in. Money is in pence.
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_variant_id',
        'product_name',
        'variant_name',
        'image',
        'unit_price',
        'quantity',
        'postage_band',
        'unit_postage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'unit_postage' => 'integer',
        ];
    }

    /**
     * The order this is a line of.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * What was bought, while it still exists - withdrawn ones included.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * The product's picture as it was bought, falling back to the picture
     * the product has now.
     *
     * The fallback is for the lines ordered before the picture was copied
     * in; everything since carries its own, so replacing a product's photo
     * does not change what an old receipt shows.
     */
    public function picture(): ?string
    {
        if (filled($this->image)) {
            return $this->image;
        }

        return $this->variant?->product?->image;
    }

    /**
     * What the line cost before postage.
     */
    public function subtotal(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
