<?php

namespace App\Support\Shop;

/**
 * Where an order has got to. Which moves are allowed depends on whether it
 * is posted or collected, and lives here alone, so the webhook and the
 * Office cannot disagree about it.
 */
enum OrderStatus: string
{
    /** Sent to Stripe, not yet paid for. */
    case Pending = 'pending';

    /** Paid for, and waiting to be posted or packed for collection. */
    case Paid = 'paid';

    /** Posted. */
    case Posted = 'posted';

    /** Arrived, as far as anyone knows. */
    case Delivered = 'delivered';

    /** Packed and waiting at Good on Paper to be collected. */
    case Ready = 'ready';

    /** Handed over. */
    case Collected = 'collected';

    /** Never paid for, or stopped and refunded before it left. */
    case Cancelled = 'cancelled';

    /** Refunded in full after it left. */
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Posted => 'Posted',
            self::Delivered => 'Delivered',
            self::Ready => 'Ready to collect',
            self::Collected => 'Collected',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * The statuses an order may move to from this one.
     *
     * @return list<self>
     */
    public function next(Fulfilment $fulfilment): array
    {
        return match ($fulfilment) {
            Fulfilment::Post => match ($this) {
                self::Pending => [self::Paid, self::Cancelled],
                self::Paid => [self::Posted, self::Cancelled],
                self::Posted => [self::Delivered, self::Refunded],
                self::Delivered => [self::Refunded],
                default => [],
            },
            Fulfilment::Collection => match ($this) {
                self::Pending => [self::Paid, self::Cancelled],
                self::Paid => [self::Ready, self::Cancelled],
                self::Ready => [self::Collected, self::Cancelled],
                self::Collected => [self::Refunded],
                default => [],
            },
        };
    }

    public function canMoveTo(self $status, Fulfilment $fulfilment): bool
    {
        return in_array($status, $this->next($fulfilment), true);
    }

    /**
     * Whether the order has left Good on Paper, so stopping it is a refund
     * rather than a cancellation.
     */
    public function hasLeft(): bool
    {
        return in_array($this, [self::Posted, self::Delivered, self::Collected, self::Refunded], true);
    }

    /**
     * The column that records when an order reached this status.
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Pending => null,
            default => $this->value.'_at',
        };
    }
}
