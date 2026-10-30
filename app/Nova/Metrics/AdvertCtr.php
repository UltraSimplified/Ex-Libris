<?php

namespace App\Nova\Metrics;

use App\Models\AdvertEvent;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class AdvertCtr extends Value
{
    public $name = 'Click-Through Rate';

    public function calculate(NovaRequest $request)
    {
        $range = $request->range ?? 30;
        $since = now()->subDays($range);

        $base = AdvertEvent::query()
            ->where('advert_id', $request->resourceId)
            ->where('created_at', '>=', $since);

        $impressions = (clone $base)->where('type', 'impression')->count();
        $clicks = (clone $base)->where('type', 'click')->count();

        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

        return $this->result($ctr)->suffix('%')->allowZeroResult();
    }

    public function ranges()
    {
        return [
            7 => __('7 Days'),
            30 => __('30 Days'),
            90 => __('90 Days'),
        ];
    }

    public function uriKey()
    {
        return 'advert-ctr';
    }
}
