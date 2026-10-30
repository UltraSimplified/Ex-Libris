<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Shop\OrderStatus;
use App\Support\Shop\OrderSummary;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An order as its customer sees it, reached by the signed link in their
 * emails - the way a guest with no account follows their order.
 */
class GuestOrderController extends Controller
{
    public function show(Order $order): Response
    {
        abort_if($order->status === OrderStatus::Pending, 404);

        return Inertia::render('shop/Order', [
            'title' => "Order {$order->reference}",
            'order' => OrderSummary::for($order),
        ]);
    }
}
