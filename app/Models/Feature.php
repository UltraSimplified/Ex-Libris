<?php

namespace App\Models;

use App\Services\Embeds\EmbedSanitiser;
use App\Services\Images\FeatureImages;
use App\Support\AffiliateLink;
use App\Support\FeatureStatus;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class Feature extends Model implements Sortable
{
    use HasFactory;
    use SortableTrait;

    // Casts of the model dates
    protected $casts = [
        'published' => 'date',
        'featured' => 'boolean',
        'sort_order' => 'integer',
        'status' => FeatureStatus::class,
    ];

    /**
     * Features are dragged into order in the Office, the highest number at
     * the top. A new feature takes the next number up, so it arrives there.
     *
     * @var array{order_column_name: string, sort_when_creating: bool, nova_order_by: string}
     */
    public array $sortable = [
        'order_column_name' => 'sort_order',
        'sort_when_creating' => true,
        'nova_order_by' => 'DESC',
    ];

    /**
     * The account Good on Paper writes under. A feature with no writer is
     * credited to it on the public pages; the writer relation stays empty.
     */
    public const GOOD_ON_PAPER_USER_ID = 91;

    /**
     * Who wrote it. Features were credited to a writer record, which was
     * a name and a photograph rather than an account; they are credited
     * to the account itself now, and writer() is what is left of that.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Tag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    /**
     * The event the feature is about, shown at the foot of it as a row
     * from the listings, so a reader can go straight on to the listing.
     *
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * The columns a byline is allowed to carry onto a public page.
     *
     * A writer is a user now, and a user record holds an email address
     * and a telephone number that are nobody's business on the front of
     * the magazine. Naming the columns keeps them off it - a byline is a
     * name, a picture and a note, and nothing else.
     *
     * @var list<string>
     */
    public const BYLINE = ['id', 'name', 'avatar', 'avatar_alt_text', 'bio', 'is_writer'];

    /**
     * Give a byline the address of its writer's page, as Our Writers makes
     * it from their name, or none for an account that is not on the
     * masthead and so has no page. Whether it is one stays here: the slug
     * says all a byline needs to.
     */
    private static function addressByline(User $writer): void
    {
        $writer->setAttribute('slug', $writer->is_writer ? Str::slug($writer->name) : null);
        $writer->makeHidden('is_writer');
    }

    /**
     * Load who wrote it, with only what a byline shows.
     *
     * A feature with no writer is given Good on Paper's account here, for the
     * public pages only. The relation itself stays empty, so Nova shows what
     * is stored rather than the account the site falls back to.
     */
    #[Scope]
    protected function withByline(Builder $query): Builder
    {
        return $query
            ->with(['user' => fn ($user) => $user->select(self::BYLINE)])
            ->afterQuery(function (EloquentCollection $features): EloquentCollection {
                $unbylined = $features->filter(fn (Feature $feature): bool => $feature->user === null);

                if ($unbylined->isNotEmpty()) {
                    $goodOnPaper = User::select(self::BYLINE)->find(self::GOOD_ON_PAPER_USER_ID) ?? new User;

                    $unbylined->each(fn (Feature $feature) => $feature->setRelation('user', $goodOnPaper));
                }

                $features->each(fn (Feature $feature) => self::addressByline($feature->user));

                return $features;
            });
    }

    /**
     * The features whoever is looking may see: every one for somebody who
     * can use the Office, so they can read a draft where it will appear,
     * and only the published ones for everybody else.
     */
    #[Scope]
    protected function visible(Builder $query): Builder
    {
        if (Gate::allows('viewNova')) {
            return $query;
        }

        return $query->where($query->qualifyColumn('status'), FeatureStatus::Published);
    }

    /**
     * The features to lead the home page with: those picked for it, in the
     * order they are put in the Office, or failing any, the newest feature
     * that is not a review (tag 5, as everywhere else on the site).
     *
     * @return EloquentCollection<int, Feature>
     */
    public static function leading(): EloquentCollection
    {
        $featured = static::query()
            ->visible()
            ->where('featured', true)
            ->orderByDesc('sort_order')
            ->orderByDesc('created_at')
            ->withByline()
            ->with('tag')
            ->get();

        if ($featured->isNotEmpty()) {
            return $featured;
        }

        return static::query()
            ->visible()
            ->where('tag_id', '!=', 5)
            ->orderByDesc('created_at')
            ->withByline()
            ->with('tag')
            ->limit(1)
            ->get();
    }

    /**
     * Send each image link in a body out with Good on Paper's affiliate
     * querystring. The campaign names the feature, so a sale can be credited to
     * the piece that sent the reader there.
     *
     * @param  array<int, object>|null  $layouts
     * @return array<int, object>|null
     */
    public function withAffiliateLinks(?array $layouts): ?array
    {
        foreach ($layouts ?? [] as $layout) {
            if (isset($layout->attributes->link) && is_string($layout->attributes->link)) {
                $layout->attributes->link = $this->affiliateUrl($layout->attributes->link);
            }
        }

        return $layouts;
    }

    /**
     * The link tagged as Good on Paper's referral, crediting this feature,
     * or null when it is not a web address.
     */
    public function affiliateUrl(string $link): ?string
    {
        return AffiliateLink::tag($link, 'feature-'.$this->id);
    }

    /**
     * Rebuild every Audio Playlist embed in the body into a safe iframe.
     *
     * The body is the Flexible field's JSON, and the playlist layout holds
     * whatever a writer pasted from Bandcamp, Spotify, Tidal or Qobuz. Like a
     * listing's audio_playlist it is rendered as markup, so it goes through
     * the same sanitiser on save and an unusable paste is stored as null.
     * Blank playlists are left alone, and the body is only written back when
     * a playlist changed, so every other layout is stored exactly as it was.
     *
     * Nova hands the body over as a collection of layouts rather than as the
     * JSON string it is stored as, so both forms are read here.
     */
    protected function sanitisePlaylists(): void
    {
        $layouts = $this->body instanceof Arrayable
            ? $this->body->toArray()
            : json_decode((string) $this->body, true);

        if (! is_array($layouts)) {
            return;
        }

        $sanitiser = app(EmbedSanitiser::class);
        $changed = false;

        foreach ($layouts as &$layout) {
            if (! is_array($layout) || ($layout['layout'] ?? null) !== 'playlist') {
                continue;
            }

            $pasted = $layout['attributes']['playlist'] ?? null;

            if (blank($pasted)) {
                continue;
            }

            $clean = is_string($pasted) ? $sanitiser->audio($pasted) : null;

            if ($clean !== $pasted) {
                $layout['attributes']['playlist'] = $clean;
                $changed = true;
            }
        }

        unset($layout);

        if ($changed) {
            $this->body = json_encode($layouts);
        }
    }

    protected static function booted(): void
    {
        static::saving(function (Feature $feature) {
            if (empty($feature->slug)) {
                $feature->slug = Str::slug($feature->title);
            }

            if ($feature->isDirty('body')) {
                $feature->sanitisePlaylists();
            }
        });

        /*
         * An image layout removed, or its image replaced, and a photo
         * dropped from a Photojournal or a whole Photojournal removed: each
         * file is deleted from the Spaces disk once the save is through.
         */
        static::saved(function (Feature $feature) {
            if (! $feature->wasChanged('body')) {
                return;
            }

            $images = app(FeatureImages::class);

            $images->deleteUnused(
                array_values(array_diff($images->paths($feature->getOriginal('body')), $images->paths($feature->body))),
                $feature
            );
        });

        static::deleted(function (Feature $feature) {
            $images = app(FeatureImages::class);

            $images->deleteUnused($images->paths($feature->body), $feature);
        });
    }
}
