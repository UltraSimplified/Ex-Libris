<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SiteVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;

/**
 * One visitor in one hour, and the pages they asked for in it. No address is
 * kept, only a hash of it keyed with the application key, and only for as
 * long as the Office's figures need it.
 */
class SiteVisit extends Model
{
    /** @use HasFactory<SiteVisitFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * The days of visits kept for the Office's longest report, today included.
     */
    public const DAYS_KEPT = 30;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'visited_at',
        'visitor_hash',
        'requests',
        'first_seen_at',
        'last_seen_at',
    ];

    /**
     * Note this hour's visit from whoever sent the request, once per hour,
     * counting every page they asked for within it and moving the last page
     * on, so that the time between the two is how long they stayed.
     *
     * Old visits are cleared here rather than on the schedule so that the
     * hashes cannot outlive the report if the scheduler is not running.
     */
    public static function record(Request $request): void
    {
        $now = now();

        self::query()->upsert(
            [[
                'visited_at' => $now->copy()->startOfHour()->toDateTimeString(),
                'visitor_hash' => self::hashFor($request),
                'requests' => 1,
                'first_seen_at' => $now->toDateTimeString(),
                'last_seen_at' => $now->toDateTimeString(),
            ]],
            ['visited_at', 'visitor_hash'],
            ['requests' => DB::raw('requests + 1'), 'last_seen_at'],
        );

        Lottery::odds(1, 100)
            ->winner(fn () => self::query()->where('visited_at', '<', self::firstKeptDay())->delete())
            ->choose();
    }

    /**
     * The start of the earliest day still kept.
     */
    public static function firstKeptDay(): CarbonInterface
    {
        return today()->subDays(self::DAYS_KEPT - 1);
    }

    /**
     * The same address and browser hash alike, which lets a visitor be
     * counted once across a whole report without storing who they are.
     */
    private static function hashFor(Request $request): string
    {
        return hash_hmac('sha256', $request->ip().'|'.$request->userAgent(), (string) config('app.key'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'requests' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
