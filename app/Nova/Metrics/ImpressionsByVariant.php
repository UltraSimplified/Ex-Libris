<?php

namespace App\Nova\Metrics;

use App\Models\AdvertEvent;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class ImpressionsByVariant extends Partition
{
    public $name = 'Impressions by Device';

    public function calculate(NovaRequest $request)
    {
        return $this->count(
            $request,
            AdvertEvent::query()
                ->where('advert_id', $request->resourceId)
                ->where('type', 'impression'),
            'variant'
        )->label(fn ($value) => match ($value) {
            'mobile' => 'Mobile',
            'desktop' => 'Desktop',
            default => 'Unknown',
        });
    }

    public function uriKey()
    {
        return 'impressions-by-variant';
    }
}
