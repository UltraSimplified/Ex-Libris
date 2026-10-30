<?php

namespace App\Nova\Metrics;

use App\Models\AdvertEvent;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;

class AdvertImpressions extends Trend
{
    public $name = 'Impressions';

    public function calculate(NovaRequest $request)
    {
        return $this->countByDays(
            $request,
            AdvertEvent::query()
                ->where('advert_id', $request->resourceId)
                ->where('type', 'impression')
        )->showSumValue();
    }

    public function ranges()
    {
        return [
            7 => __('7 Days'),
            14 => __('14 Days'),
            30 => __('30 Days'),
            60 => __('60 Days'),
            90 => __('90 Days'),
        ];
    }

    public function uriKey()
    {
        return 'advert-impressions';
    }
}
