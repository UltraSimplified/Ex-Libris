<?php

namespace App\Services\Shop;

use App\Support\Shop\Fulfilment;
use App\Support\Shop\PostageQuote;

/**
 * The one place postage is worked out, so the basket, the checkout and the
 * order cannot disagree.
 *
 * Only posted orders pay: each item's band, once per item, and never more
 * than the cap however many items there are. Collected orders, and those
 * with nothing to send, pay nothing.
 */
class Postage
{
    /**
     * @param  iterable<array{amount: int, quantity: int}>  $items  each item's band amount in pence, and how many
     */
    public static function quote(iterable $items, Fulfilment $fulfilment, int $cap): PostageQuote
    {
        if ($fulfilment !== Fulfilment::Post) {
            return new PostageQuote(0, 0);
        }

        $total = 0;

        foreach ($items as $item) {
            $total += $item['amount'] * $item['quantity'];
        }

        return new PostageQuote(min($total, max(0, $cap)), $total);
    }
}
