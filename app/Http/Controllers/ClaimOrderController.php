<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Adds a guest's order to the account they are signed in to.
 *
 * Reached only by the signed link in the order's confirmation email, so
 * only whoever reads that inbox can claim it - signing up with somebody
 * else's email address does not bring their orders with it. Somebody not
 * signed in is sent to sign in or register first, and comes back here.
 */
class ClaimOrderController extends Controller
{
    public function __invoke(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->paid_at === null, 404);

        $user = $request->user();

        if ($order->user_id !== null && $order->user_id !== $user->id) {
            abort(403, 'That order already belongs to another account.');
        }

        if ($order->user_id === null) {
            $order->user()->associate($user)->save();
        }

        return to_route('dashboard.orders.show', $order);
    }
}
