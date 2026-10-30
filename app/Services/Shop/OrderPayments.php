<?php

namespace App\Services\Shop;

use App\Events\Shop\OrderPaid;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Services\Shop\Payments\PaymentGateway;
use App\Support\Shop\OrderStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What Stripe tells the shop about payments, applied to its orders.
 *
 * Stripe repeats a webhook it is not sure arrived, and can send them out of
 * order, so each of these looks at where the order has got to, under a
 * lock, and does nothing if it has already moved on.
 *
 * Only Checkout sessions and payments carrying one of our order ids are
 * touched; anything else on the account is left alone.
 */
class OrderPayments
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * A Checkout session was completed. If the money is in, the order is
     * paid: it takes the customer's details and addresses as Stripe
     * collected them, and the stock comes down.
     *
     * @param  array<string, mixed>  $session
     */
    public function completed(array $session): ?Order
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            return null;
        }

        $order = DB::transaction(function () use ($session): ?Order {
            $order = $this->orderFor($session, lock: true);

            if ($order === null || $order->status !== OrderStatus::Pending) {
                return null;
            }

            $customer = (array) ($session['customer_details'] ?? []);
            $shipping = Arr::get($session, 'collected_information.shipping_details')
                ?? Arr::get($session, 'shipping_details');

            $order->fill([
                'email' => $customer['email'] ?? $order->email,
                'name' => $customer['name'] ?? $order->name,
                'phone' => $customer['phone'] ?? null,
                'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
                'billing_address' => StripeAddress::toOrder($customer['address'] ?? null, $customer['name'] ?? null),
                'shipping_address' => StripeAddress::toOrder($shipping['address'] ?? null, $shipping['name'] ?? null),
            ]);

            $order->oversold = $this->takeStock($order);
            $order->transitionTo(OrderStatus::Paid);

            return $order;
        });

        if ($order === null) {
            return null;
        }

        $this->recordReceipt($order);

        OrderPaid::dispatch($order);

        return $order;
    }

    /**
     * A Checkout session lapsed unpaid, so its order is cancelled.
     *
     * @param  array<string, mixed>  $session
     */
    public function expired(array $session): ?Order
    {
        return DB::transaction(function () use ($session): ?Order {
            $order = $this->orderFor($session, lock: true);

            if ($order === null || $order->status !== OrderStatus::Pending) {
                return null;
            }

            $order->transitionTo(OrderStatus::Cancelled);

            return $order;
        });
    }

    /**
     * Some or all of a payment was refunded, from the Office or from
     * Stripe's dashboard.
     *
     * A full refund before the order left cancels it and puts the stock
     * back; after it left, the order is marked refunded. A part refund only
     * records how much went back.
     *
     * @param  array<string, mixed>  $charge
     */
    public function refunded(array $charge): ?Order
    {
        $paymentIntent = $charge['payment_intent'] ?? null;

        if (! is_string($paymentIntent)) {
            return null;
        }

        return DB::transaction(function () use ($charge, $paymentIntent): ?Order {
            $order = Order::query()
                ->where('stripe_payment_intent_id', $paymentIntent)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                return null;
            }

            $order->refunded_amount = max($order->refunded_amount, (int) ($charge['amount_refunded'] ?? 0));
            $order->save();

            if ($order->refunded_amount < $order->total) {
                return $order;
            }

            if ($order->status->canMoveTo(OrderStatus::Cancelled, $order->fulfilment) && $order->status !== OrderStatus::Pending) {
                $this->returnStock($order);
                $order->transitionTo(OrderStatus::Cancelled);
            } elseif ($order->status->canMoveTo(OrderStatus::Refunded, $order->fulfilment)) {
                $order->transitionTo(OrderStatus::Refunded);
            }

            return $order;
        });
    }

    /**
     * Put an order's items back on the shelf, as when it is cancelled
     * before it leaves.
     */
    public function returnStock(Order $order): void
    {
        $order->items->each(function (OrderItem $item): void {
            ProductVariant::withTrashed()
                ->whereKey($item->product_variant_id)
                ->whereNotNull('stock')
                ->increment('stock', $item->quantity);
        });
    }

    /**
     * Take an order's items off the shelf. Two customers can pay for the
     * last one at once; the second still has their order, but it is
     * flagged so the office can sort it out.
     *
     * @return bool whether any line could not be covered by the stock left
     */
    private function takeStock(Order $order): bool
    {
        $oversold = false;

        foreach ($order->items as $item) {
            $variant = ProductVariant::withTrashed()->lockForUpdate()->find($item->product_variant_id);

            if ($variant === null || $variant->stock === null) {
                continue;
            }

            $oversold = $oversold || $variant->stock < $item->quantity;
            $variant->stock = max(0, $variant->stock - $item->quantity);
            $variant->save();
        }

        return $oversold;
    }

    /**
     * Keep the link to Stripe's receipt. An order is paid whether or not
     * Stripe answers this, so a failure is reported and let go.
     */
    private function recordReceipt(Order $order): void
    {
        if ($order->stripe_payment_intent_id === null) {
            return;
        }

        try {
            $order->update(['receipt_url' => $this->gateway->receiptUrl($order->stripe_payment_intent_id)]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The order a Checkout session was for: it must carry our order id and
     * be the session that order was sent to.
     *
     * @param  array<string, mixed>  $session
     */
    private function orderFor(array $session, bool $lock): ?Order
    {
        $orderId = Arr::get($session, 'metadata.order_id');

        if (! is_numeric($orderId) || ! is_string($session['id'] ?? null)) {
            return null;
        }

        return Order::query()
            ->whereKey((int) $orderId)
            ->where('stripe_checkout_session_id', $session['id'])
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->with('items')
            ->first();
    }
}
