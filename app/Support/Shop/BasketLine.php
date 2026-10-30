<?php

namespace App\Support\Shop;

use App\Models\ProductVariant;

/**
 * One line of the basket: a variant and how many of it.
 */
final readonly class BasketLine
{
    public function __construct(
        public ProductVariant $variant,
        public int $quantity,
    ) {}

    /**
     * What the line costs before postage, in pence.
     */
    public function subtotal(): int
    {
        return $this->variant->currentPrice() * $this->quantity;
    }

    /**
     * Whether it can still be bought: in stock, and as many as are asked for.
     */
    public function isAvailable(): bool
    {
        return $this->variant->isInStock()
            && ($this->variant->stock === null || $this->quantity <= $this->variant->stock);
    }

    /**
     * Its postage band's amount and the quantity, for Postage::quote().
     *
     * @return array{amount: int, quantity: int}
     */
    public function postageItem(): array
    {
        return [
            'amount' => $this->variant->product->postageBand->amount,
            'quantity' => $this->quantity,
        ];
    }
}
