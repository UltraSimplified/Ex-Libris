<?php

namespace App\Models;

use Database\Factories\SiteVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Lottery;

/**
 * One visitor on one day. No address is kept, only a hash of it keyed with
 * the application key, and only for as long as the Office's figures need it.
 */
class SiteVisit extends Model
{
    /** @use HasFactory<SiteVisitFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * The days of visits the Office reports on, today included.
     */
    public const DAYS = 7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'visited_on',
        'visitor_hash',
    ];

    /**
     * Note today's visit from whoever sent the request, once per day.
     *
     * Old visits are cleared here rather than on the schedule so that the
     * hashes cannot outlive the report if the scheduler is not running.
     */
    public static function record(Request $request): void
    {
        self::query()->insertOrIgnore([
            'visited_on' => today()->toDateString(),
            'visitor_hash' => self::hashFor($request),
        ]);

        Lottery::odds(1, 100)
            ->winner(fn () => self::query()->where('visited_on', '<', self::firstReportedDay())->delete())
            ->choose();
    }

    /**
     * The earliest date the Office reports on.
     */
    public static function firstReportedDay(): string
    {
        return today()->subDays(self::DAYS - 1)->toDateString();
    }

    /**
     * The same address and browser hash alike, which lets a visitor be
     * counted once across the week without storing who they are.
     */
    private static function hashFor(Request $request): string
    {
        return hash_hmac('sha256', $request->ip().'|'.$request->userAgent(), (string) config('app.key'));
    }
}
