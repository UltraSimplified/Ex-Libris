<?php

namespace App\Nova\Cards;

use App\Models\Listing;
use App\Services\Listings\ListingCsvExport;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Card;

/**
 * One-click download of the listings CSV for the issue being laid out.
 *
 * The Nova action asks for a range; this asks for nothing and assumes the
 * usual one, so the layout artist can pull the file straight off the
 * dashboard. The link is signed when the card renders and is good for two
 * hours, which comfortably outlives the page.
 */
class ListingsExport extends Card
{
    public $width = '1/2';

    public function component(): string
    {
        return 'gop-listings-export';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $from = now()->addMonthNoOverflow()->startOfMonth();
        $to = now()->addMonthNoOverflow()->endOfMonth();
        $month = $from->format('F');

        return array_merge(parent::jsonSerialize(), [
            'heading' => $month."'s Listings",
            'count' => Listing::query()->runningBetween($from, $to)->count(),
            'countLabel' => 'Listings',
            'buttonText' => 'Download '.$month.' Listings',
            'filename' => app(ListingCsvExport::class)->filename($from, $to),
            'url' => URL::temporarySignedRoute('exports.listings', now()->addHours(2), [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]),
            'link' => '/resources/listings',
            'linkText' => 'View all current listings',
        ]);
    }
}
