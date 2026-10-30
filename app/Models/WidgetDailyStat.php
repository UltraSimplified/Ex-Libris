<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A widget's views and clicks for one day.
 */
class WidgetDailyStat extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'widget_id',
        'date',
        'views',
        'clicks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'views' => 'integer',
            'clicks' => 'integer',
        ];
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    /**
     * Add one to today's count of the given kind for a widget, in a single
     * statement whether or not today's row exists yet.
     *
     * @param  'views'|'clicks'  $column
     */
    public static function record(Widget $widget, string $column): void
    {
        static::query()->upsert(
            [['widget_id' => $widget->id, 'date' => today()->toDateString(), $column => 1]],
            ['widget_id', 'date'],
            [$column => DB::raw($column.' + 1')],
        );
    }
}
