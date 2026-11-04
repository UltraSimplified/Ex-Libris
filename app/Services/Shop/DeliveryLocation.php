<?php

namespace App\Services\Shop;

use App\Models\Order;
use App\Services\Geocoding\PostcodeGeocoder;
use App\Support\Shop\Fulfilment;

/**
 * Where a posted order is going, looked up once and kept on the order.
 *
 * Stripe hands the delivery address over in the webhook, which is no place
 * to wait on postcodes.io, so the lookup happens the first time anything
 * actually wants the map - the queued confirmation email, usually, and the
 * customer's own page otherwise. Both ask here, and whichever asks first
 * pays for it.
 *
 * Postcode accuracy is all this gets: the middle of the dozen or so
 * addresses the postcode covers, which is honest for "roughly here" and
 * is all the customer needs of an address they gave us themselves.
 */
final class DeliveryLocation
{
    public function __construct(private PostcodeGeocoder $geocoder) {}

    /**
     * Where the order is going, or null if it is being collected, has no
     * delivery postcode, or postcodes.io does not know it.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function for(Order $order): ?array
    {
        if ($order->fulfilment === Fulfilment::Collection) {
            return null;
        }

        if ($order->delivery_latitude !== null && $order->delivery_longitude !== null) {
            return [
                'latitude' => (float) $order->delivery_latitude,
                'longitude' => (float) $order->delivery_longitude,
            ];
        }

        $place = $this->geocoder->locate($order->shipping_address['postcode'] ?? null);

        if ($place === null) {
            return null;
        }

        $order->delivery_latitude = $place['latitude'];
        $order->delivery_longitude = $place['longitude'];
        $order->saveQuietly();

        return $place;
    }
}
