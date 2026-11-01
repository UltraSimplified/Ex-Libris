<?php

namespace App\Models;

use App\Support\Brand;
use App\Support\Widgets\WidgetDensity;
use App\Support\Widgets\WidgetFont;
use App\Support\Widgets\WidgetOptions;
use App\Support\Widgets\WidgetSizeMode;
use App\Support\Widgets\WidgetTemplate;
use Database\Factories\WidgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A gig widget: a venue's or a promoter's upcoming events, embedded in
 * somebody else's website.
 *
 * It belongs to the venue or promoter rather than to whoever made it, so
 * one the Office makes for a venue nobody has claimed yet passes to the
 * venue's owner along with the venue, and the snippet already on the
 * venue's site keeps working.
 */
class Widget extends Model
{
    /** @use HasFactory<WidgetFactory> */
    use HasFactory;

    /**
     * Who it belongs to and its key are set by the controller, never by
     * whatever a form posts.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'template',
        'density',
        'options',
        'colours',
        'font',
        'size_mode',
        'width',
        'height',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template' => WidgetTemplate::class,
            'density' => WidgetDensity::class,
            'font' => WidgetFont::class,
            'size_mode' => WidgetSizeMode::class,
            'options' => 'array',
            'colours' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Every widget gets a key of its own on the way in, which is what the
     * snippet carries rather than the guessable id.
     */
    protected static function booted(): void
    {
        static::creating(function (Widget $widget): void {
            $widget->key ??= static::freshKey();
        });
    }

    /**
     * A random key no other widget has.
     */
    public static function freshKey(): string
    {
        do {
            $key = Str::random(22);
        } while (static::where('key', $key)->exists());

        return $key;
    }

    /**
     * The venue or promoter whose events it shows.
     *
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Who made it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Its views and clicks, a row a day.
     *
     * @return HasMany<WidgetDailyStat, $this>
     */
    public function dailyStats(): HasMany
    {
        return $this->hasMany(WidgetDailyStat::class);
    }

    /**
     * Its settings, with a default for anything not set.
     */
    public function settings(): WidgetOptions
    {
        return WidgetOptions::from($this->options);
    }

    /**
     * The colours it is painted in: its own if it has been given some,
     * otherwise its owner's, so changing a venue's colours restyles every
     * widget that follows them.
     */
    public function brand(): Brand
    {
        if (filled($this->colours)) {
            return Brand::from($this->colours);
        }

        return $this->owner?->brand() ?? Brand::from([]);
    }

    /**
     * Whether it shows a promoter's events, which may be at any venue.
     */
    public function isForPromoter(): bool
    {
        return $this->owner_type === Promoter::class;
    }

    /**
     * The listings column that ties an event to the owner.
     */
    public function listingsColumn(): string
    {
        return $this->isForPromoter() ? 'promoter_id' : 'venue_id';
    }
}
