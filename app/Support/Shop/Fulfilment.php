<?php

namespace App\Support\Shop;

/**
 * How an order reaches the customer.
 */
enum Fulfilment: string
{
    /**
     * Posted to a UK address, paying each item's postage up to the cap.
     */
    case Post = 'post';

    /**
     * Picked up from Good on Paper, free.
     */
    case Collection = 'collection';

    public function label(): string
    {
        return match ($this) {
            self::Post => 'Post to me',
            self::Collection => 'Collect from Good on Paper',
        };
    }
}
