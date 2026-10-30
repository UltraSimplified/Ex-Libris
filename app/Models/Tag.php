<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    /**
     * Sent with every tag, so a feature's category can link to the page of
     * features filed under it.
     *
     * @var list<string>
     */
    protected $appends = ['slug'];

    /**
     * Find the tag a features page is filtered by, from its slug.
     *
     * Tags are named in the Office and carry no slug of their own, so it is
     * worked out from the name; there are a handful of them, so they are
     * matched here rather than in SQL.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::all()->first(fn (self $tag): bool => $tag->slug === $slug);
    }

    /**
     * The tag's name as it appears in a URL: "Photojournals" becomes
     * "photojournals".
     */
    protected function slug(): Attribute
    {
        return Attribute::get(fn (): string => Str::slug($this->tag));
    }
}
