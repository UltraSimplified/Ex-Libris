<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ShopSettings;
use App\Support\Shop\Fulfilment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Packing slips for the orders the Office picked, one page each.
 *
 * Reached only by the signed link the Office's Print packing slips action
 * issues.
 */
class PackingSlipController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('orders'))));

        $orders = Order::query()
            ->whereKey($ids)
            ->with('items')
            ->orderBy('id')
            ->get();

        abort_if($orders->isEmpty(), 404);

        $settings = ShopSettings::current();

        return Pdf::loadView('reports.packing-slips', [
            'orders' => $orders,
            'collection' => Fulfilment::Collection,
            'collectionName' => $settings->collection_name,
        ])
            ->setPaper('a4')
            ->download('packing-slips-'.now()->format('Y-m-d').'.pdf');
    }
}
