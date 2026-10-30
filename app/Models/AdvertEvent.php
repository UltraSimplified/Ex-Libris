<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvertEvent extends Model
{
    const null UPDATED_AT = null;

    protected $guarded = [];

    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'advert_id',
        'type',
        'page',
        'variant',
        'placement',
        // Visitor location (resolved via GeoResolver at ingestion)
        'country_code',
        'region',
        'city',
        'locality_bucket',
    ];

    /**
     * Where on a page an advert can be shown: the sponsorship panel at the
     * foot, or a slot among the listings.
     *
     * @var list<string>
     */
    public const PLACEMENTS = ['panel', 'list'];

    /**
     * The placement a request names, or null when it names none of them, so
     * a hand-made request cannot add its own to the report.
     */
    public static function placementFrom(mixed $value): ?string
    {
        return in_array($value, self::PLACEMENTS, true) ? $value : null;
    }

    /**
     * The advert, archived or not: an archived advert's events are kept.
     */
    public function advert()
    {
        return $this->belongsTo(Advert::class)->withTrashed();
    }

    public function scopeImpressions($q)
    {
        return $q->where('type', 'impression');
    }

    public function scopeClicks($q)
    {
        return $q->where('type', 'click');
    }
}
