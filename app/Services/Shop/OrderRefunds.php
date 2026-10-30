<?php

namespace App\Services\Shop;

use App\Mail\Shop\OrderRefunded;
use App\Models\Order;
use App\Models\User;
use App\Services\Shop\Payments\PaymentGateway;
use App\Support\Shop\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Giving money back from the Office.
 *
 * The refund is made at Stripe first; only once Stripe has taken it is the
 * order changed. Stripe's charge.refunded webhook follows and finds the
 * order already up to date. Each refund carries a key made from the order
 * and how much had been refunded before it, so a double click cannot refund
 * twice.
 */
class OrderRefunds
{
    public function __construct(
        private PaymentGateway $gateway,
        private OrderPayments $payments,
    ) {}

    /**
     * Stop an order that has not left yet and give all the money back. Its
     * items go back on the shelf.
     */
    public function cancel(Order $order, ?User $by): void
    {
        if ($order->status->hasLeft() || ! $order->status->canMoveTo(OrderStatus::Cancelled, $order->fulfilment) || $order->status === OrderStatus::Pending) {
            throw new InvalidArgumentException("Order {$order->reference} cannot be cancelled now.");
        }

        $amount = $this->refundAtStripe($order, null);

        DB::transaction(function () use ($order, $amount, $by): void {
            $order->refunded_amount += $amount;
            $this->payments->returnStock($order->loadMissing('items'));
            $order->transitionTo(OrderStatus::Cancelled);
            $order->addNote('Cancelled and refunded '.$this->pounds($amount).$this->byWhom($by).'.');
        });

        $this->tell($order, $amount);
    }

    /**
     * Give back some or all of what was paid for an order that has left.
     * A full refund marks it refunded; a part refund only records the sum.
     *
     * @param  int|null  $amount  pence, or null for everything still refundable
     */
    public function refund(Order $order, ?int $amount, ?User $by): void
    {
        if (! $order->status->hasLeft() || $order->status === OrderStatus::Refunded) {
            throw new InvalidArgumentException("Order {$order->reference} has not left yet - cancel it instead.");
        }

        if ($amount !== null && ($amount < 1 || $amount > $order->refundable())) {
            throw new InvalidArgumentException('That is more than is left to refund.');
        }

        $amount = $this->refundAtStripe($order, $amount);

        DB::transaction(function () use ($order, $amount, $by): void {
            $order->refunded_amount += $amount;
            $order->save();

            if ($order->refundable() === 0) {
                $order->transitionTo(OrderStatus::Refunded);
            }

            $order->addNote('Refunded '.$this->pounds($amount).$this->byWhom($by).'.');
        });

        $this->tell($order, $amount);
    }

    /**
     * Ask Stripe for the refund. Refunding everything is sent without an
     * amount, so Stripe gives back whatever is left.
     *
     * @return int what was refunded, in pence
     */
    private function refundAtStripe(Order $order, ?int $amount): int
    {
        if ($order->stripe_payment_intent_id === null) {
            throw new InvalidArgumentException("Order {$order->reference} has no payment to refund.");
        }

        $whole = $amount === null || $amount === $order->refundable();

        $this->gateway->refund(
            $order->stripe_payment_intent_id,
            $whole ? null : $amount,
            "refund-{$order->id}-{$order->refunded_amount}-".($whole ? 'all' : $amount),
        );

        return $whole ? $order->refundable() : $amount;
    }

    private function tell(Order $order, int $amount): void
    {
        if (filled($order->email)) {
            Mail::to($order->email, $order->name)->queue(new OrderRefunded($order, $amount));
        }
    }

    private function pounds(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }

    private function byWhom(?User $by): string
    {
        return $by ? ' by '.$by->name : '';
    }
}
