<?php

namespace App\Models;

use App\Models\Concerns\HasBrand;
use App\Models\Concerns\HasReferredWebsite;
use App\Services\Images\BrandImage;
use App\Services\Widgets\WidgetCache;
use Database\Factories\FestivalFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A promoter's events across one or more venues, under a name and
 * branding of its own.
 *
 * Everything it is known by is its own: a festival often looks nothing
 * like the promoter behind it, so nothing here falls back to the
 * promoter's details. A colour left unset falls back to Good on Paper's,
 * as a promoter's does.
 *
 * @property int $promoter_id
 */
class Festival extends Model
{
    /** @use HasFactory<FestivalFactory> */
    use HasBrand, HasFactory, HasReferredWebsite;

    /**
     * Everything an owner may change by name. The promoter is set once,
     * when the festival is made, and the logo and banner are assigned by
     * whoever stored them, as for a promoter.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'website',
        'email',
        'phone',
        'about',
    ];

    /**
     * The colour the festival's mark sits on, read wherever a listing's
     * cover is drawn.
     *
     * @var list<string>
     */
    protected $appends = ['mark_background'];

    /**
     * Its name and colours are in its gig widgets, which are rendered afresh
     * whenever it is saved. Going, it takes its widgets with it and then its
     * pictures; its listings stay, freed by the database.
     */
    protected static function booted(): void
    {
        static::saved(fn (Festival $festival) => app(WidgetCache::class)->forgetOwner(Festival::class, $festival->id));

        static::deleting(fn (Festival $festival) => $festival->widgets()->delete());

        static::deleted(function (Festival $festival): void {
            app(BrandImage::class)->delete($festival->logo);
            app(BrandImage::class)->delete($festival->banner);
        });
    }

    /**
     * The colour behind the festival's mark on a cover: its own background
     * colour, or Good on Paper's where it has none.
     */
    protected function markBackground(): Attribute
    {
        return Attribute::get(fn (): string => $this->brand()->background);
    }

    /**
     * Whether everything in the festival has been. One with nothing in it
     * yet has not finished: it has not started.
     */
    public function hasFinished(): bool
    {
        return $this->listings()->exists()
            && ! $this->listings()->current()->exists();
    }

    /**
     * The promoter putting the festival on, which it belongs to for good.
     *
     * @return BelongsTo<Promoter, $this>
     */
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class);
    }

    /**
     * The events in the festival.
     *
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * The gig widgets showing the festival's events on other websites.
     *
     * @return MorphMany<Widget, $this>
     */
    public function widgets(): MorphMany
    {
        return $this->morphMany(Widget::class, 'owner');
    }
}
