<?php

namespace App\Models;

use App\Models\Concerns\HasBrand;
use App\Services\Geocoding\PostcodeGeocoder;
use App\Services\Widgets\WidgetCache;
use App\Support\AffiliateLink;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Venue extends Model
{
    use HasBrand, HasFactory;

    /**
     * Everything the owner may change from the dashboard by name. Whether a
     * venue stocks the magazine is the office's call, not the venue's, so it
     * is left out. The cover photo is set from the same form but assigned by
     * the controller once stored, so an uploaded filename cannot arrive
     * mass-assigned. The brand colours are added by HasBrand.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'address',
        'town',
        'postcode',
        'url',
        'phone',
        'email',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Put the venue on the map when its postcode is set or changed, or when
     * it has a postcode but no place yet.
     *
     * A place set by hand in the Office is kept: a save that changes the
     * latitude or longitude itself is not looked up again, so the pin can be
     * nudged onto the door and stay there.
     *
     * Saving it also refreshes its gig widgets.
     */
    protected static function booted(): void
    {
        static::saving(function (Venue $venue): void {
            if ($venue->isDirty(['latitude', 'longitude'])) {
                return;
            }

            $unplaced = $venue->latitude === null || $venue->longitude === null;

            if (! $venue->isDirty('postcode') && ! $unplaced) {
                return;
            }

            $place = app(PostcodeGeocoder::class)->locate($venue->postcode);

            $venue->latitude = $place['latitude'] ?? null;
            $venue->longitude = $place['longitude'] ?? null;
        });

        // Its name and colours are in its gig widgets.
        static::saved(fn (Venue $venue) => app(WidgetCache::class)->forgetOwner(Venue::class, $venue->id));
    }

    /**
     * The venue's website, tagged as Good on Paper's referral so the venue
     * can see who sent the reader, as a listing's ticket link is. A link
     * that is not a web address is passed on as it was.
     *
     * Appended only on the venue's own page, where the link is drawn.
     */
    protected function websiteLink(): Attribute
    {
        return Attribute::get(function (): ?string {
            $url = trim((string) $this->url);

            if ($url === '') {
                return null;
            }

            return AffiliateLink::tag($url, 'venue-'.$this->id) ?? $url;
        });
    }

    /**
     * The account that manages this venue, if anyone does.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The events taking place here.
     *
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * The gig widgets showing this venue's events on other websites.
     *
     * @return MorphMany<Widget, $this>
     */
    public function widgets(): MorphMany
    {
        return $this->morphMany(Widget::class, 'owner');
    }
}
