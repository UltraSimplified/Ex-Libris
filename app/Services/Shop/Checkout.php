<?php

namespace App\Services\Shop;

use App\Models\Order;
use App\Models\User;
use App\Services\Shop\Payments\PaymentGateway;
use App\Support\Shop\BasketLine;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\OrderStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns a basket into an order and sends the customer to Stripe to pay.
 *
 * The order is made first, Pending, with its lines and totals copied from
 * the basket as it stands, so what Stripe charges and what the order says
 * cannot differ. Stripe's webhook marks it paid; nothing here does.
 *
 * Every line goes to Stripe with its price written in, rather than as a
 * Stripe product, so the shop never writes to the Stripe catalogue.
 */
class Checkout
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * Make the order and open its Checkout session.
     *
     * @throws ValidationException when the basket is empty or holds something that cannot be bought
     */
    public function start(Basket $basket, ?User $user): CheckoutResult
    {
        $lines = $basket->lines();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['basket' => 'Your basket is empty.']);
        }

        if ($lines->contains(fn (BasketLine $line): bool => ! $line->isAvailable())) {
            throw ValidationException::withMessages([
                'basket' => 'Something in your basket has sold out, or there are fewer left than you asked for.',
            ]);
        }

        $fulfilment = $basket->fulfilment();
        $order = $this->makeOrder($lines, $fulfilment, $user);

        try {
            $session = $this->gateway->createCheckoutSession(
                $this->parameters($order, $lines, $user ? $this->gateway->customerFor($user) : null),
            );
        } catch (Throwable $exception) {
            $order->transitionTo(OrderStatus::Cancelled);
            report($exception);

            throw ValidationException::withMessages([
                'basket' => 'We could not reach our payment provider. Nothing has been charged - please try again in a moment.',
            ]);
        }

        $order->update(['stripe_checkout_session_id' => $session->id]);

        return new CheckoutResult($order, $session);
    }

    /**
     * The Pending order, its lines and totals copied from the basket.
     *
     * @param  Collection<int, BasketLine>  $lines
     */
    private function makeOrder(Collection $lines, Fulfilment $fulfilment, ?User $user): Order
    {
        $subtotal = Basket::subtotal($lines);
        $postage = Basket::postage($lines, $fulfilment);

        return DB::transaction(function () use ($lines, $fulfilment, $user, $subtotal, $postage): Order {
            $order = Order::query()->create([
                'user_id' => $user?->id,
                'email' => $user?->email,
                'name' => $user?->name,
                'status' => OrderStatus::Pending,
                'fulfilment' => $fulfilment,
                'subtotal' => $subtotal,
                'shipping' => $postage->amount,
                'total' => $subtotal + $postage->amount,
                'postage_capped' => $postage->isCapped(),
            ]);

            $order->items()->createMany($lines->map(fn (BasketLine $line): array => [
                'product_variant_id' => $line->variant->id,
                'product_name' => $line->variant->product->name,
                'variant_name' => $line->variant->displayName(),
                'unit_price' => $line->variant->currentPrice(),
                'quantity' => $line->quantity,
                'postage_band' => $line->variant->product->postageBand->name,
                'unit_postage' => $line->variant->product->postageBand->amount,
            ])->all());

            return $order;
        });
    }

    /**
     * What Stripe is asked for.
     *
     * A posted order asks for a UK delivery address and pays its postage as
     * Stripe's shipping line; a collected one is asked for no delivery
     * address at all, and its shipping line says where to collect it.
     *
     * @param  Collection<int, BasketLine>  $lines
     * @return array<string, mixed>
     */
    public function parameters(Order $order, Collection $lines, ?string $customerId): array
    {
        $collect = $order->fulfilment === Fulfilment::Collection;
        $metadata = ['order_id' => (string) $order->id, 'reference' => (string) $order->reference];

        return array_filter([
            'mode' => 'payment',
            'line_items' => $lines->map(fn (BasketLine $line): array => [
                'quantity' => $line->quantity,
                'price_data' => [
                    'currency' => 'gbp',
                    'unit_amount' => $line->variant->currentPrice(),
                    'product_data' => array_filter([
                        'name' => $line->variant->product->name,
                        'description' => $line->variant->displayName() === $line->variant::STANDARD
                            ? null
                            : $line->variant->displayName(),
                        'images' => array_filter([$line->variant->product->imageUrl()]) ?: null,
                        'metadata' => [
                            'product_id' => (string) $line->variant->product_id,
                            'variant_id' => (string) $line->variant->id,
                        ],
                    ], fn ($value): bool => $value !== null),
                ],
            ])->values()->all(),
            'shipping_address_collection' => $collect ? null : ['allowed_countries' => ['GB']],
            'shipping_options' => [[
                'shipping_rate_data' => [
                    'type' => 'fixed_amount',
                    'display_name' => $collect
                        ? 'Collect from Good on Paper, Lansdown, Stroud'
                        : 'UK postage',
                    'fixed_amount' => ['amount' => $order->shipping, 'currency' => 'gbp'],
                ],
            ]],
            'billing_address_collection' => 'required',
            'phone_number_collection' => ['enabled' => true],
            'customer' => $customerId,
            'customer_update' => $customerId ? ['address' => 'auto', 'shipping' => 'auto', 'name' => 'auto'] : null,
            'customer_creation' => $customerId ? null : 'if_required',
            'client_reference_id' => (string) $order->id,
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata],
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.cancelled'),
            'expires_at' => now()->addMinutes(max(30, (int) config('shop.checkout_expires_after')))->getTimestamp(),
        ], fn ($value): bool => $value !== null);
    }
}
