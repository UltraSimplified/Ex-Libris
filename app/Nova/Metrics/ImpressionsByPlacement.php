<?php

namespace App\Nova\Metrics;

use App\Models\AdvertEvent;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

/**
 * Where on the page an advert's impressions came from: the sponsorship panel
 * or the slots among the listings. Impressions from before placement was
 * recorded are counted as not recorded.
 */
class ImpressionsByPlacement extends Partition
{
    public $name = 'Impressions by Placement';

    public function calculate(NovaRequest $request)
    {
        return $this->count(
            $request,
            AdvertEvent::query()
                ->where('advert_id', $request->resourceId)
                ->where('type', 'impression'),
            'placement'
        )->label(fn ($value) => match ($value) {
            'panel' => 'Sponsorship panel',
            'list' => 'In-list',
            default => 'Not recorded',
        });
    }

    public function uriKey()
    {
        return 'impressions-by-placement';
    }
}
