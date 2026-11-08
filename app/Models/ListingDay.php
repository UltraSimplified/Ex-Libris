<?php

namespace App\Models;

use Database\Factories\ListingDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day a listing is on, for a listing that is not on every day of its
 * run: a pub quiz every other Tuesday, a festival at weekends.
 */
class ListingDay extends Model
{
    /** @use HasFactory<ListingDayFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'listing_id',
        'day',
    ];

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * A day, handed out as a day for the same reason as a listing's dates.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date:Y-m-d',
        ];
    }
}
