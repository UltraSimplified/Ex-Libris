<?php

namespace App\Models;

use App\Services\Listings\ListingLineUp;
use App\Services\Widgets\WidgetCache;
use App\Support\AffiliateLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Listing extends Model
{
    //
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'meta_description',
        'thumbnail',
        'line_up',
        'gallery',
        'event_link',
        'ticket_price',
        'button_text',
        'venue_id',
        'promoter_id',
        'sold_out',
        'category_id',
        'start_date',
        'end_date',
        'event_time_description',
        'additional_date_information',
    ];

    /**
     * The two dates are days, so they are handed out as days.
     *
     * A plain 'date' cast is still serialised as the instant that day begins
     * in London, which in summer is 23:00 the evening before in UTC. Anything
     * reading the first ten characters of that, as the edit form does, got
     * the day before the one that was saved.
     */
    protected function casts(): array
    {
        return [
            'sold_out' => 'boolean',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    /**
     * SQL for the last day a listing runs.
     *
     * end_date is only filled in for events that span more than one day, so
     * a single-day listing finishes on the day it starts. Comparing against
     * end_date alone silently drops every listing without one.
     */
    private const LAST_DAY = 'DATE(COALESCE(end_date, start_date))';

    /**
     * Listings running at any point between two dates, inclusive.
     *
     * An overlap test, so a run that began before the window and finishes
     * inside it counts, as does one that starts inside and finishes after.
     */
    public function scopeRunningBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $to)
            ->whereRaw(self::LAST_DAY.' >= ?', [Carbon::parse($from)->toDateString()]);
    }

    /**
     * Listings in the order they finish: what ends today first, a run that
     * goes on to the end of the month last. Ties go to whatever started
     * first, then by title.
     */
    public function scopeFinishingSoonest(Builder $query): Builder
    {
        return $query
            ->orderByRaw(self::LAST_DAY.' asc')
            ->orderBy('start_date')
            ->orderBy('title');
    }

    /**
     * Listings running on a given day.
     */
    public function scopeRunningOn(Builder $query, mixed $date): Builder
    {
        return $query->runningBetween($date, $date);
    }

    /**
     * Listings that have not finished yet, including ones already under way.
     *
     * Dates are compared as dates: start_date is stored at midnight, so
     * comparing it against the current time hides everything starting today.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereRaw(self::LAST_DAY.' >= ?', [today()->toDateString()]);
    }

    /**
     * Listings somebody put in by hand rather than the magazine import.
     *
     * Ones sent in through the dashboard, which always record the account
     * behind them, and ones given a cover image, which an import never sets.
     * Either way a person stands behind the listing and the magazine's own
     * copy of the same event should give way to it.
     *
     * Grouped, because this scope is chained with others.
     */
    public function scopeHandAdded(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNotNull('listings.user_id')
                ->orWhere(function (Builder $query): void {
                    $query->whereNotNull('listings.thumbnail')
                        ->where('listings.thumbnail', '!=', '');
                });
        });
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class)->withDefault([
            'id' => 0,
            'name' => 'Venue to be confirmed',
        ]);
    }

    /**
     * Where the listing's tickets or details are, tagged as Good on Paper's
     * referral so the venue or promoter can see who sent the reader: the
     * event's own link, or failing that the venue's website. A link that is
     * not a web address is passed on as it was.
     *
     * Appended only where the button is drawn, the listing's own page, as
     * reading it needs the venue loaded.
     */
    protected function ticketLink(): Attribute
    {
        return Attribute::get(function (): ?string {
            $link = trim((string) ($this->event_link ?: $this->venue?->url));

            if ($link === '') {
                return null;
            }

            return AffiliateLink::tag($link, 'listing-'.$this->id) ?? $link;
        });
    }

    /**
     * Whether the event costs nothing, as far as its free-text price can
     * say: the word "free" with no figure beside it. The figure is what
     * keeps "£10 (adult), children go free" from counting. A listing with
     * no price may simply not have been given one, so is not called free.
     *
     * Appended only where the answer is used, as with the ticket link.
     */
    protected function isFree(): Attribute
    {
        return Attribute::get(function (): bool {
            $price = (string) $this->ticket_price;

            return preg_match('/\bfree\b/i', $price) === 1 && preg_match('/\d/', $price) === 0;
        });
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Promoter, $this>
     */
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The people who have saved this event.
     *
     * @return BelongsToMany<User, $this>
     */
    public function bookmarkedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks')->withTimestamps();
    }

    protected static function booted(): void
    {
        static::saving(function ($listing) {
            if (empty($listing->slug)) {
                $listing->slug = Str::slug($listing->title);
            }

            // The line-up is rendered as markup, so whatever wrote it - the
            // Office, the dashboard, a duplicate - only a clean one is kept.
            if ($listing->isDirty('line_up')) {
                $listing->line_up = app(ListingLineUp::class)->clean($listing->line_up);
            }
        });

        // The gig widgets showing it are rendered afresh on their next view.
        static::saved(fn (Listing $listing) => app(WidgetCache::class)->forgetListing($listing));
        static::deleted(fn (Listing $listing) => app(WidgetCache::class)->forgetListing($listing));
    }
}
