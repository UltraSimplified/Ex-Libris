<?php

namespace App\Support\Shop;

/**
 * What an order pays for postage, in pence, and whether the cap brought it
 * down from what its items would have come to.
 */
final readonly class PostageQuote
{
    public function __construct(
        public int $amount,
        public int $uncapped,
    ) {}

    /**
     * Whether the cap made this cheaper than paying per item.
     */
    public function isCapped(): bool
    {
        return $this->amount < $this->uncapped;
    }

    /**
     * @return array{amount: int, uncapped: int, capped: bool}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'uncapped' => $this->uncapped,
            'capped' => $this->isCapped(),
        ];
    }
}
