<?php

namespace App\Models;

use App\Models\Concerns\HasBrand;
use App\Services\Widgets\WidgetCache;
use App\Support\AffiliateLink;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The owner column was added in a loop over two tables, which Larastan
 * cannot follow when it reads the migrations, so it is declared here.
 *
 * @property int|null $user_id
 */
class Promoter extends Model
{
    use HasBrand, HasFactory;

    /**
     * Everything the owner may change from the dashboard by name. The logo
     * and banner are set from the same form but assigned by the controller
     * once stored, so an uploaded filename cannot arrive mass-assigned. The
     * brand colours are added by HasBrand.
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
     * Its name and colours are in its gig widgets, which are rendered afresh
     * whenever it is saved.
     */
    protected static function booted(): void
    {
        static::saved(fn (Promoter $promoter) => app(WidgetCache::class)->forgetOwner(Promoter::class, $promoter->id));
    }

    /**
     * The promoter's website, tagged as Good on Paper's referral as a
     * venue's is. A link that is not a web address is passed on as it was.
     *
     * Appended only on the promoter's own page, where the link is drawn.
     */
    protected function websiteLink(): Attribute
    {
        return Attribute::get(function (): ?string {
            $website = trim((string) $this->website);

            if ($website === '') {
                return null;
            }

            return AffiliateLink::tag($website, 'promoter-'.$this->id) ?? $website;
        });
    }

    /**
     * The account that manages this promoter, if anyone does.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The events this promoter has put on.
     *
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * The gig widgets showing this promoter's events on other websites.
     *
     * @return MorphMany<Widget, $this>
     */
    public function widgets(): MorphMany
    {
        return $this->morphMany(Widget::class, 'owner');
    }
}
