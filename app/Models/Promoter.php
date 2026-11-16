<?php

namespace App\Models;

use App\Models\Concerns\HasBrand;
use App\Models\Concerns\HasReferredWebsite;
use App\Services\Widgets\WidgetCache;
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
    use HasBrand, HasFactory, HasReferredWebsite;

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
     *
     * Its festivals go before it, one at a time through the model, so each
     * takes its widgets and pictures with it: the database would remove the
     * rows on its own, but nothing else.
     */
    protected static function booted(): void
    {
        static::saved(fn (Promoter $promoter) => app(WidgetCache::class)->forgetOwner(Promoter::class, $promoter->id));

        static::deleting(fn (Promoter $promoter) => $promoter->festivals()->get()->each->delete());
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
     * The festivals this promoter puts on, each with its own name and look.
     *
     * @return HasMany<Festival, $this>
     */
    public function festivals(): HasMany
    {
        return $this->hasMany(Festival::class);
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
