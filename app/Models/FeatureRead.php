<?php

namespace App\Models;

use Database\Factories\FeatureReadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * How a feature was read on one day, as totals: how many people began it,
 * how long they spent on it between them, and how many reached its end.
 * Reported by the feature page itself, since a feature is often the last
 * page of a visit and nothing on the server sees how long that lasted.
 */
class FeatureRead extends Model
{
    /** @use HasFactory<FeatureReadFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * The longest a single report can add: half an hour, past which a page
     * left open is no longer being read.
     */
    public const LONGEST_REPORT = 1800;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'feature_id',
        'read_on',
        'read_count',
        'seconds_read',
        'reached_end_count',
    ];

    /**
     * Add one report to today's totals for the feature. A read can report
     * more than once - each time the reader leaves the tab - so only its
     * first report counts it as a read, and only one counts it as finished.
     */
    public static function record(Feature $feature, int $seconds, bool $isNewRead, bool $reachedEnd): void
    {
        $reads = (int) $isNewRead;
        $endings = (int) $reachedEnd;

        self::query()->upsert(
            [[
                'feature_id' => $feature->getKey(),
                'read_on' => today()->toDateString(),
                'read_count' => $reads,
                'seconds_read' => $seconds,
                'reached_end_count' => $endings,
            ]],
            ['feature_id', 'read_on'],
            [
                'read_count' => DB::raw("read_count + {$reads}"),
                'seconds_read' => DB::raw("seconds_read + {$seconds}"),
                'reached_end_count' => DB::raw("reached_end_count + {$endings}"),
            ],
        );
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_on' => 'date',
            'read_count' => 'integer',
            'seconds_read' => 'integer',
            'reached_end_count' => 'integer',
        ];
    }
}
