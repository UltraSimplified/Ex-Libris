<?php

namespace App\Services\Shop;

use App\Mail\Shop\OrderPosted;
use App\Mail\Shop\OrderReadyForCollection;
use App\Models\Order;
use App\Support\Shop\OrderStatus;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * The office moving paid orders on: posting them, or packing them for
 * collection and handing them over. The customer hears about the two that
 * matter to them - posted, and ready to collect.
 */
class OrderFulfilment
{
    /**
     * Posted, with a tracking number if there is one.
     */
    public function post(Order $order, ?string $trackingNumber = null): void
    {
        if (filled($trackingNumber)) {
            $order->tracking_number = trim($trackingNumber);
        }

        $order->transitionTo(OrderStatus::Posted);

        $this->tell($order, new OrderPosted($order));
    }

    /**
     * Arrived, as far as anyone knows.
     */
    public function deliver(Order $order): void
    {
        $order->transitionTo(OrderStatus::Delivered);
    }

    /**
     * Packed and waiting at Good on Paper.
     */
    public function ready(Order $order): void
    {
        $order->transitionTo(OrderStatus::Ready);

        $this->tell($order, new OrderReadyForCollection($order));
    }

    /**
     * Handed over, noting who to if it was not the customer.
     */
    public function collect(Order $order, ?string $collectedBy = null): void
    {
        $order->transitionTo(OrderStatus::Collected);

        if (filled($collectedBy)) {
            $order->addNote('Collected by '.trim($collectedBy).'.');
        }
    }

    private function tell(Order $order, Mailable $mail): void
    {
        if (filled($order->email)) {
            Mail::to($order->email, $order->name)->queue($mail);
        }
    }
}
