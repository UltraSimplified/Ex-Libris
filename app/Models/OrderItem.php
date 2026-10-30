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
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * What was bought, while it still exists - withdrawn ones included.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * What the line cost before postage.
     */
    public function subtotal(): int
    {
        return $this->unit_price * $this->quantity;
    }
}
