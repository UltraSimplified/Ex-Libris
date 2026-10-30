<?php

namespace App\Nova\Dashboards;

use App\Nova\Cards\AdvertReach;
use App\Nova\Cards\FeaturesByCategory;
use App\Nova\Cards\ListingsExport;
use App\Nova\Cards\PromoterLeaders;
use App\Nova\Cards\VenueLeaders;
use App\Nova\Cards\WriterLeaders;
use App\Nova\Metrics\OrdersAwaiting;
use App\Nova\Metrics\StalePendingOrders;
use App\Support\Shop\Fulfilment;
use Laravel\Nova\Card;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    /**
     * Whether the shop's three orders cards lead the dashboard. Off for
     * now, while the shop is quiet; they are coming back, so they are
     * switched off here rather than taken out.
     */
    private const SHOWS_ORDERS = false;

    /**
     * The name shown as the dashboard's heading and in the sidebar.
     */
    public function name(): string
    {
        return 'Office';
    }

    /**
     * The name shown in the sidebar, which Nova derives separately.
     */
    public function label(): string
    {
        return $this->name();
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array<int, Card>
     */
    public function cards(): array
    {
        $orders = self::SHOWS_ORDERS ? [
            new OrdersAwaiting(Fulfilment::Post),
            new OrdersAwaiting(Fulfilment::Collection),
            StalePendingOrders::make(),
        ] : [];

        return [
            ...$orders,
            FeaturesByCategory::make(),
            AdvertReach::make(),
            ListingsExport::make(),
            WriterLeaders::make(),
            PromoterLeaders::make(),
            VenueLeaders::make(),
        ];
    }
}
