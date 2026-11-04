<?php

namespace App\Services\Shop;

use App\Models\Order;
use App\Models\ShopSettings;
use App\Services\Images\StaticMap;
use App\Support\Shop\Fulfilment;

/**
 * The maps an order is shown with: where to collect it from, or where it
 * is going.
 *
 * The customer's own page draws the collection map itself, with the same
 * Leaflet map a venue's page uses, so it only wants the place. An email
 * can run nothing, so it gets a picture instead. Both come from here so
 * the page and the email cannot end up pinning different things.
 */
final class OrderMaps
{
    public function __construct(
        private DeliveryLocation $delivery,
        private StaticMap $maps,
    ) {}

    /**
     * Where the shop hands collection orders over, for a map drawn in the
     * browser. Null until the office's collection address has a postcode
     * postcodes.io knows.
     *
     * @return array{name: string, latitude: float, longitude: float}|null
     */
    public function collectionPlace(): ?array
    {
        $settings = $this->settings();

        if ($settings->latitude === null || $settings->longitude === null) {
            return null;
        }

        return [
            'name' => (string) $settings->collection_name,
            'latitude' => (float) $settings->latitude,
            'longitude' => (float) $settings->longitude,
        ];
    }

    /**
     * The shop's settings, on the map.
     *
     * They were seeded by a migration rather than saved, so the row has no
     * place until something asks for one. Saving is what looks the postcode
     * up, so an unplaced row is saved here to put it on the map without the
     * office having to open the form.
     */
    private function settings(): ShopSettings
    {
        $settings = ShopSettings::current();

        if ($settings->latitude === null || $settings->longitude === null) {
            $settings->save();
        }

        return $settings;
    }

    /**
     * A picture of where to collect from, for an email.
     */
    public function collectionPicture(): ?string
    {
        $place = $this->collectionPlace();

        if ($place === null) {
            return null;
        }

        return $this->maps->url($place['latitude'], $place['longitude']);
    }

    /**
     * A picture of where a posted order is going. Null for one being
     * collected, or when the delivery postcode could not be placed.
     */
    public function deliveryPicture(Order $order): ?string
    {
        if ($order->fulfilment !== Fulfilment::Post) {
            return null;
        }

        $place = $this->delivery->for($order);

        if ($place === null) {
            return null;
        }

        return $this->maps->url($place['latitude'], $place['longitude']);
    }

    /**
     * How wide and tall the pictures are, so an email can size the image
     * it is given rather than guessing.
     *
     * @return array{width: int, height: int}
     */
    public function pictureSize(): array
    {
        return ['width' => $this->maps->width(), 'height' => $this->maps->height()];
    }
}
