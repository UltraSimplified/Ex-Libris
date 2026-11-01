<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Something the shop sells: a print, a shirt, a poster, a record.
 *
 * What is bought is one of its variants, which carry the price and the
 * stock. A print has a single variant with no colour or size; a t-shirt has
 * one for every colour and size.
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The disk product pictures are kept on, the same public one as the rest
     * of the site's pictures, so Stripe's checkout page can show them too.
     */
    public const DISK = 'do';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_category_id',
        'postage_band_id',
        'name',
        'slug',
        'summary',
        'description',
        'image',
        'position',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * The shelf this is on.
     *
     * @return BelongsTo<ShopCategory, $this>
     */
    public function shopCategory(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class);
    }

    /**
     * What it costs to post one.
     *
     * @return BelongsTo<PostageBand, $this>
     */
    public function postageBand(): BelongsTo
    {
        return $this->belongsTo(PostageBand::class);
    }

    /**
     * The colours and sizes it comes in, in the order the office set.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    /**
     * The pictures after the main one.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * What the public may see and buy: published, on a published shelf,
     * with a postage band and something to buy.
     *
     * A product without a band could not be posted, and one without a
     * variant has no price, so both stay out of sight however they are
     * marked.
     */
    #[Scope]
    protected function onSale(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('postage_band_id')
            ->whereHas('shopCategory', fn (Builder $category) => $category->where('is_published', true))
            ->whereHas('variants');
    }

    /**
     * In the order the office has put them, then by name.
     */
    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    /**
     * The main picture's address, or none.
     */
    public function imageUrl(): ?string
    {
        return filled($this->image) ? Storage::disk(self::DISK)->url($this->image) : null;
    }

    /**
     * Whether every variant has run out. Something with no variants cannot
     * be bought either.
     */
    public function isSoldOut(): bool
    {
        return $this->variants->every(fn (ProductVariant $variant): bool => ! $variant->isInStock());
    }
}
