<?php

namespace App\Models;

use Database\Factories\PostageBandFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What it costs to post one of a kind of thing. There are five, made by
 * migrations and only ever edited in the Office: No postage, for donations
 * and anything else never posted or collected, then Letter, Large letter,
 * Parcel and Tube. Amounts are in pence, per item.
 *
 * No postage alone has needs_delivery off: a basket holding only things in
 * it is neither posted nor collected.
 */
class PostageBand extends Model
{
    /** @use HasFactory<PostageBandFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'amount',
        'needs_delivery',
        'description',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'needs_delivery' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * The products posted in this band.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The bands in the order the Office lists them, smallest first.
     */
    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
