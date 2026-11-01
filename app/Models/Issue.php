<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Issue extends Model
{
    use HasFactory;

    /**
     * Whether a file of the issue has been put up to download.
     *
     * Read from the record alone. Asking the Spaces disk about every cover
     * on an archive page would be a request apiece; the download itself
     * checks the file is really there.
     *
     * @return Attribute<bool, never>
     */
    protected function downloadable(): Attribute
    {
        return Attribute::get(fn (): bool => filled($this->pdf));
    }

    /**
     * The name the issue's file downloads under. It keeps the extension of
     * what was put up: one or two of the old issues are a photograph of
     * the printed copy rather than a PDF, and would not open as one.
     */
    public function downloadName(): string
    {
        $extension = strtolower(pathinfo((string) $this->pdf, PATHINFO_EXTENSION)) ?: 'pdf';

        return "GOP — Issue #{$this->issue_number} ({$this->month_year}).{$extension}";
    }

    /**
     * The writers with an article in this issue.
     *
     * @return BelongsToMany<User, $this>
     */
    public function writers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Every issue but the one on sale now - the highest numbered, as the
     * archive has it - which is not given away until the next is out.
     */
    public function scopeBackIssues(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('issue_number'), '<', static::query()->max('issue_number') ?? 0);
    }
}
