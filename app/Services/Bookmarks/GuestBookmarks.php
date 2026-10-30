<?php

namespace App\Services\Bookmarks;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Bookmarks kept in a cookie, for somebody who has not signed in.
 *
 * The cookie holds the listing ids, oldest first, joined with dots
 * ("412.398.377"), and lasts a year from the last change. It is left
 * unencrypted (see bootstrap/app.php): encryption would roughly double it
 * against the 4 KB a browser allows, and editing it does no harm, since
 * every id is read as a number and checked against the listings.
 *
 * Ids for events that have finished, been withdrawn or been deleted drop
 * out when it is read, and the cookie is rewritten without them, which
 * stands in for the overnight bookmarks:prune.
 */
class GuestBookmarks implements BookmarkStore
{
    public const COOKIE = 'gop_bookmarks';

    /**
     * The most a browser keeps. Saving one more lets the oldest go.
     */
    public const LIMIT = 100;

    public const MINUTES = 60 * 24 * 365;

    /**
     * The ids still worth keeping, once they have been checked.
     *
     * @var list<int>|null
     */
    private ?array $ids = null;

    /**
     * @param  list<int>  $stored
     */
    public function __construct(private array $stored) {}

    public static function fromRequest(Request $request): self
    {
        return new self(self::parse($request->cookie(self::COOKIE)));
    }

    /**
     * The whole numbers in the cookie, each once, newest kept.
     *
     * @return list<int>
     */
    public static function parse(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        return collect(explode('.', $value))
            ->filter(fn (string $id): bool => ctype_digit($id) && (int) $id > 0)
            ->map(fn (string $id): int => (int) $id)
            ->reverse()
            ->unique()
            ->take(self::LIMIT)
            ->reverse()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        if ($this->ids === null) {
            $this->ids = $this->stillCurrent($this->stored);

            if ($this->ids !== $this->stored) {
                $this->save();
            }
        }

        return $this->ids;
    }

    public function add(Listing $listing): void
    {
        $ids = array_values(array_diff($this->ids(), [$listing->id]));
        $ids[] = $listing->id;

        $this->ids = array_slice($ids, -self::LIMIT);
        $this->save();
    }

    public function remove(Listing $listing): void
    {
        $this->ids = array_values(array_diff($this->ids(), [$listing->id]));
        $this->save();
    }

    /**
     * @return Builder<Listing>
     */
    public function listings(): Builder
    {
        return Listing::query()->whereIn('listings.id', $this->ids());
    }

    /**
     * The ids of events not yet over, in the order they were saved.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function stillCurrent(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $current = Listing::query()
            ->whereIn('listings.id', $ids)
            ->current()
            ->pluck('listings.id')
            ->all();

        return array_values(array_intersect($ids, $current));
    }

    /**
     * Write the ids back to the browser, or clear the cookie once there are
     * none left.
     */
    private function save(): void
    {
        if ($this->ids === []) {
            Cookie::expire(self::COOKIE);

            return;
        }

        Cookie::queue(self::COOKIE, implode('.', $this->ids), self::MINUTES);
    }
}
