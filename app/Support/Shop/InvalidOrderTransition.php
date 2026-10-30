<?php

namespace App\Support\Shop;

use DomainException;

/**
 * An order was asked to move to a status it cannot reach from where it is.
 */
class InvalidOrderTransition extends DomainException
{
    public static function between(OrderStatus $from, OrderStatus $to, Fulfilment $fulfilment): self
    {
        return new self("A {$fulfilment->value} order cannot go from {$from->value} to {$to->value}.");
    }
}
