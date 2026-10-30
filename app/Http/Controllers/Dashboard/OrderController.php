<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Shop\OrderSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where an account finds what it has paid for: shop purchases now, and in
 * time advert payments and ticket orders.
 *
 * Only orders that were paid for are listed - not ones abandoned on
 * Stripe's page - and an account sees only its own. A guest order joins an
 * account by the signed link in its confirmation email.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = $request->user()
            ->orders()
            ->paidFor()
            ->withSum('items as item_count', 'quantity')
            ->latest('paid_at')
            ->paginate(20)
            ->through(fn (Order $order): array => [
                'reference' => $order->reference,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'fulfilment' => $order->fulfilment->value,
                'item_count' => (int) $order->item_count,
                'total' => $order->total,
            ]);

        return Inertia::render('dashboard/orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()->id && $order->paid_at !== null, 404);

        return Inertia::render('dashboard/orders/Show', [
            'order' => [
                ...OrderSummary::for($order),
                'billing_address' => Order::addressLines($order->billing_address),
                'receipt_url' => $order->receipt_url,
                'refunded_amount' => $order->refunded_amount,
                'posted_at' => $order->posted_at?->toIso8601String(),
                'ready_at' => $order->ready_at?->toIso8601String(),
                'collected_at' => $order->collected_at?->toIso8601String(),
                'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            ],
        ]);
    }
}
