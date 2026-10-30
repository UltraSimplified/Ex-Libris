<?php

namespace App\Models;

use App\Services\Adverts\AdvertRotation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Advert extends Model
{
    //
    use HasFactory;

    /**
     * Deleting an advert archives it: it leaves the rotation, and its events
     * and report are kept to show what an advert here has done.
     */
    use SoftDeletes;

    public function events(): HasMany
    {
        return $this->hasMany(AdvertEvent::class);
    }

    // Casts of the model dates
    protected $fillable = [
        'type',
        'location',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sponsorship' => 'boolean',
        'run_from' => 'date',
        'run_to' => 'date',
    ];

    public function scopeLive($query)
    {
        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('run_from')->orWhereDate('run_from', '<=', today()))
            ->where(fn ($q) => $q->whereNull('run_to')->orWhereDate('run_to', '>=', today()));
    }

    /**
     * The adverts shown in the panel at the foot of every page.
     *
     * @param  Builder<Advert>  $query
     */
    public function scopeSponsorships(Builder $query): void
    {
        $query->where('sponsorship', true);
    }

    /**
     * The adverts shown among the listings, stockists and features.
     *
     * @param  Builder<Advert>  $query
     */
    public function scopeInList(Builder $query): void
    {
        $query->where('sponsorship', false);
    }

    /**
     * Which slot the advert takes, as the report and the Office name it.
     *
     * @return Attribute<string, never>
     */
    protected function slot(): Attribute
    {
        return Attribute::get(fn (): string => $this->sponsorship ? 'Sponsorship panel' : 'In-list');
    }

    /**
     * The site's cached list of live adverts is dropped whenever an advert
     * changes, so switching one on or off in the Office shows at once.
     * Archiving and restoring are changes too.
     */
    protected static function booted(): void
    {
        static::saved(fn () => AdvertRotation::forget());
        static::deleted(fn () => AdvertRotation::forget());
        static::restored(fn () => AdvertRotation::forget());
    }
}
