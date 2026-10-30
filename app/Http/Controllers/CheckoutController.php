<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Shop\Basket;
use App\Services\Shop\Checkout;
use App\Support\Shop\OrderSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * From the basket to Stripe and back.
 *
 * Coming back to the thank-you page does not mark an order paid - anyone
 * could load that address. Stripe's webhook does; the page only shows how
 * far it has got.
 */
class CheckoutController extends Controller
{
    /**
     * The session key for the order this visitor was last sent to pay for.
     */
    private const ORDER = 'shop.checkout_order';

    /**
     * Make the order and send the customer to Stripe.
     */
    public function store(Request $request, Checkout $checkout, Basket $basket): SymfonyResponse
    {
        $result = $checkout->start($basket, $request->user());

        $request->session()->put(self::ORDER, $result->order->id);

        return Inertia::location($result->session->url);
    }

    /**
     * Where Stripe sends the customer once they have paid. Only the visitor
     * who was sent to pay sees the order here; anyone else is sent to the
     * shop. Their basket has been paid for, so it is emptied.
     */
    public function success(Request $request, Basket $basket): Response|RedirectResponse
    {
        $order = Order::query()
            ->where('stripe_checkout_session_id', (string) $request->query('session_id'))
            ->first();

        if ($order === null || $request->session()->get(self::ORDER) !== $order->id) {
            return to_route('Shop');
        }

        $basket->clear();

        return Inertia::render('shop/Thanks', [
            'title' => 'Thank you',
            'order' => fn (): array => OrderSummary::for($order->fresh()),
            'orderUrl' => $order->guestUrl(),
            'claimUrl' => $order->user_id === null ? $order->claimUrl() : null,
        ]);
    }

    /**
     * Where Stripe sends a customer who turns back. Their basket is as they
     * left it; the order they did not pay for lapses on its own.
     */
    public function cancelled(): RedirectResponse
    {
        return to_route('basket.show');
    }
}
