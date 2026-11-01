<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Nova\Auth\Impersonatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Impersonatable, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * What this account is allowed to be, as granted by the office. Kept
     * out of $fillable deliberately: a permission is given in the Office,
     * never submitted from a form.
     *
     * @var list<string>
     */
    public const ROLES = ['is_venue_mgr', 'is_promoter', 'is_writer'];

    /**
     * The office's own accounts, the admins: let into the Office, and
     * holding every role whether or not one is ticked.
     *
     * @var list<string>
     */
    public const OFFICE_EMAILS = [
        'robin@ultrasimplified.com',
        'adam@goodonpaper.info',
        'alex@goodonpaper.info',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * The promoters this account manages.
     *
     * @return HasMany<Promoter, $this>
     */
    public function promoters(): HasMany
    {
        return $this->hasMany(Promoter::class);
    }

    /**
     * The venues this account manages.
     *
     * @return HasMany<Venue, $this>
     */
    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    /**
     * What this account has written for the magazine.
     *
     * @return HasMany<Feature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    /**
     * The issues of the magazine this writer has an article in, kept by
     * hand in the Office: most of it was printed rather than put online.
     * Newest first, as the Office's Issues field and the writer's page
     * list them.
     *
     * @return BelongsToMany<Issue, $this>
     */
    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class)
            ->withTimestamps()
            ->orderByDesc('issues.issue_number');
    }

    /**
     * Accounts the office has marked as writing for the magazine.
     *
     * The column rather than hasRole(), deliberately: staff hold every
     * role without being marked with any, and Our Writers is a masthead
     * of people who write, not a list of everybody who could.
     */
    #[Scope]
    protected function writers(Builder $query): Builder
    {
        return $query->where('is_writer', true);
    }

    /**
     * The writers still writing for the magazine, in masthead order: by
     * first name, as Our Writers lists them. A writer the office has made
     * inactive is an alumnus and is left off.
     */
    #[Scope]
    protected function masthead(Builder $query): Builder
    {
        return $query->writers()->where('is_active_writer', true)->orderBy('name');
    }

    /**
     * Order accounts by the last word of their name, the way a masthead
     * is set. MySQL and SQLite share no function for it, so
     * the expression is picked per driver.
     *
     * @throws InvalidArgumentException
     */
    #[Scope]
    protected function orderBySurname(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException("Order direction must be 'asc' or 'desc', got '{$direction}'.");
        }

        $surname = match ($query->getConnection()->getDriverName()) {
            'sqlite' => "replace(name, rtrim(name, replace(name, ' ', '')), '')",
            default => "substring_index(name, ' ', -1)",
        };

        return $query->orderByRaw("{$surname} {$direction}");
    }

    /**
     * Every address on this account.
     *
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Where this account is billed.
     *
     * @return HasOne<Address, $this>
     */
    public function billingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', Address::BILLING);
    }

    /**
     * Where the post goes, when that is somewhere other than the billing
     * address. Absent means the two are the same.
     *
     * @return HasOne<Address, $this>
     */
    public function deliveryAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', Address::DELIVERY);
    }

    /**
     * Where to send anything this account orders.
     */
    public function postalAddress(): ?Address
    {
        return $this->deliveryAddress ?? $this->billingAddress;
    }

    /**
     * Everything this account has ordered from the shop, paid for or not.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The events this account has saved to come back to.
     *
     * @return BelongsToMany<Listing, $this>
     */
    public function bookmarks(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'bookmarks')->withTimestamps();
    }

    /**
     * Whether the office has granted this account a role.
     *
     * Staff hold all of them without being marked with any: somebody who
     * can reach the Office can already do everything a role permits, and
     * ticking three boxes on every staff account to say so would only be
     * something else to keep in step.
     */
    public function hasRole(string $role): bool
    {
        if (! in_array($role, self::ROLES, true)) {
            return false;
        }

        return Gate::forUser($this)->allows('viewNova') || (bool) $this->{$role};
    }

    /**
     * Whether this account has a dashboard: it manages a venue, promotes or
     * writes, which staff all do. Anybody else has nothing there yet.
     */
    public function hasDashboard(): bool
    {
        return collect(self::ROLES)->contains(fn (string $role): bool => $this->hasRole($role));
    }

    /**
     * Whether staff may switch to this account from the Office, to see its
     * dashboard as its owner does. Only accounts with a dashboard, and
     * never another member of staff: switching to one would lend its Office
     * to whoever switched. Who may switch is the trait's call, which is
     * whoever can reach the Office.
     */
    public function canBeImpersonated(): bool
    {
        return $this->hasDashboard() && ! Gate::forUser($this)->allows('viewNova');
    }

    /**
     * The roles this account holds, as the front end reads them.
     *
     * @return array<string, bool>
     */
    public function roles(): array
    {
        return collect(self::ROLES)
            ->mapWithKeys(fn (string $role): array => [$role => $this->hasRole($role)])
            ->all();
    }

    /**
     * Whether this account speaks for a promoter or a venue.
     *
     * Only such an account - one the office has agreed and linked to a
     * promoter or venue - adds and edits listings from the dashboard. There
     * is no queue: what it saves goes straight up.
     */
    public function managesListings(): bool
    {
        return $this->promoters()->exists() || $this->venues()->exists();
    }

    /**
     * The promoters this account may put a listing under.
     *
     * Only its own - a listing is attributed to whoever is actually
     * promoting it. When an existing listing is being edited, whatever it
     * already names is kept in the set so that saving cannot quietly
     * reattribute somebody else's event.
     *
     * @return Builder<Promoter>
     */
    public function listablePromoters(?Listing $keep = null): Builder
    {
        return Promoter::query()->where(function (Builder $query) use ($keep): void {
            $query->whereIn('promoters.id', $this->promoters()->select('promoters.id'));

            if ($keep?->promoter_id !== null) {
                $query->orWhere('promoters.id', $keep->promoter_id);
            }
        });
    }

    /**
     * The venues this account may put a listing at.
     *
     * A venue owner who does nothing else is listing what is on in their
     * own building, so they get their own venues and nothing else. Anyone
     * promoting events - around the district, or at their own place and
     * other people's - gets the full list.
     *
     * @return Builder<Venue>
     */
    public function listableVenues(?Listing $keep = null): Builder
    {
        // A promoter takes events around the district, so they get the whole
        // list even when they run a building of their own: the workshop they
        // are putting on at somebody else's hall is not at their venue just
        // because they happen to have one.
        if ($this->promoters()->exists() || ! $this->venues()->exists()) {
            return Venue::query();
        }

        return Venue::query()->where(function (Builder $query) use ($keep): void {
            $query->whereIn('venues.id', $this->venues()->select('venues.id'));

            if ($keep?->venue_id !== null) {
                $query->orWhere('venues.id', $keep->venue_id);
            }
        });
    }

    /**
     * Every listing this account may edit: its own, plus anything running
     * under a promoter or at a venue it manages.
     *
     * A venue owner reaches events other people put on in their building,
     * which is the point - they are the ones who find out first when a
     * date moves.
     *
     * @return Builder<Listing>
     */
    public function editableListings(): Builder
    {
        return Listing::query()->where(function (Builder $query): void {
            $query->where('listings.user_id', $this->id)
                ->orWhereIn('listings.promoter_id', $this->promoters()->select('promoters.id'))
                ->orWhereIn('listings.venue_id', $this->venues()->select('venues.id'));
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_venue_mgr' => 'boolean',
            'is_promoter' => 'boolean',
            'is_writer' => 'boolean',
            'is_active_writer' => 'boolean',
        ];
    }
}
