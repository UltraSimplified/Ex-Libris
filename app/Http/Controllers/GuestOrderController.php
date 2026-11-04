<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Shop\OrderStatus;
use App\Support\Shop\OrderSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An order as its customer sees it, reached by the signed link in their
 * emails.
 *
 * An order on an account opens only for that account, so a forwarded email
 * shows nobody else its address: anybody not signed in signs in first and
 * comes back. A guest's order has no account to check against, so for that
 * alone the link itself is what lets them in, until it is claimed.
 */
class GuestOrderController extends Controller
{
    public function show(Request $request, Order $order): Response|RedirectResponse
    {
        abort_if($order->status === OrderStatus::Pending, 404);

        if ($order->user_id !== null) {
            return $this->forItsAccount($request, $order);
        }

        return Inertia::render('shop/Order', [
            'title' => "Order {$order->reference}",
            'order' => OrderSummary::for($order),
        ]);
    }

    private function forItsAccount(Request $request, Order $order): RedirectResponse
    {
        if ($request->user() === null) {
            return redirect()->guest(route('login'));
        }

        abort_unless($request->user()->id === $order->user_id, 403, 'That order belongs to another account.');

        return to_route('dashboard.orders.show', $order);
    }
}
