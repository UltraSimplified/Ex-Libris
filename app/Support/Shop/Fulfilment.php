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

    /**
     * Neither posted nor collected, as nothing in the order needs to be: a
     * donation, say. The basket decides this, never the customer.
     */
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Post => 'Post to me',
            self::Collection => 'Collect from Good on Paper',
            self::None => 'Nothing to post or collect',
        };
    }

    /**
     * What the office has to do with an order, as its emails and
     * notifications put it: "New order GOP-10001 to post".
     */
    public function officeTask(): string
    {
        return match ($this) {
            self::Post => 'to post',
            self::Collection => 'to collect',
            self::None => 'with nothing to send',
        };
    }

    /**
     * The ways a customer may choose between, for a basket with something
     * in it to send.
     *
     * @return list<self>
     */
    public static function choices(): array
    {
        return [self::Post, self::Collection];
    }
}
