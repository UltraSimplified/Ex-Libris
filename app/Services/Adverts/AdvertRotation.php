<?php

namespace App\Services\Adverts;

use App\Models\Advert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Which advert a page view gets.
 *
 * Any number of adverts can be live at once, and they take page views in
 * turn: each view goes to whichever live advert has been served least, ties
 * going to the oldest. With nothing else in play that is plain round robin,
 * A, B, C, A, B, C. The one thing in play is the visitor: nobody is given
 * the advert they saw last. When that turns a view away from the advert
 * whose turn it was, the counts send the next view its way, so the shares
 * stay level, a view or two apart.
 *
 * The counts start again from nothing whenever the line-up changes, so an
 * advert switched on mid-campaign takes its share from its first view
 * rather than every view until it has caught up with the others.
 *
 * The live list is cached for ten minutes, and forgotten as soon as an
 * advert is saved or deleted; the choice from it is made for every view.
 *
 * Sponsorships and in-list adverts take turns separately (AdvertPool): each
 * pool has its own list, counts and memory of what the visitor saw last, so
 * a view of one never moves the other's turn on.
 */
final class AdvertRotation
{
    public const CACHE_KEY = 'adverts.live';

    /**
     * Where a visitor's session keeps the advert they were last shown.
     */
    public const SESSION_KEY = 'advert.last';

    public function __construct(private readonly AdvertPool $pool = AdvertPool::InList) {}

    /**
     * The rotation for one pool of adverts.
     */
    public static function for(AdvertPool $pool): self
    {
        return new self($pool);
    }

    /**
     * How long a line-up's counts are kept once nothing is adding to them.
     */
    private const COUNT_DAYS = 30;

    /**
     * What a page carries of an advert.
     *
     * @var list<string>
     */
    private const COLUMNS = ['id', 'title', 'owner', 'desktop_image', 'mobile_image', 'alt_text'];

    /**
     * Every advert in this pool live now, oldest first.
     *
     * @return list<array{id: int, title: ?string, owner: ?string, desktop_image: ?string, mobile_image: ?string, alt_text: ?string}>
     */
    public function live(): array
    {
        return Cache::remember(
            self::cacheKey($this->pool),
            now()->addMinutes(10),
            fn (): array => Advert::query()
                ->live()
                ->when(
                    $this->pool === AdvertPool::Sponsorship,
                    fn ($query) => $query->sponsorships(),
                    fn ($query) => $query->inList(),
                )
                ->orderBy('id')
                ->get(self::COLUMNS)
                ->toArray(),
        );
    }

    /**
     * The advert for this page view, or null when none is live. The view is
     * counted to it, and the visitor's session remembers it.
     *
     * @return array{id: int, title: ?string, owner: ?string, desktop_image: ?string, mobile_image: ?string, alt_text: ?string}|null
     */
    public function next(?Request $request = null): ?array
    {
        $live = $this->live();

        if ($live === []) {
            return null;
        }

        $session = $request?->hasSession() ? $request->session() : null;
        $advert = $this->choose($live, $session?->get($this->sessionKey()));

        $session?->put($this->sessionKey(), $advert['id']);

        return $advert;
    }

    /**
     * Drop the cached list, so a change made in the Office shows at once.
     */
    public static function forget(): void
    {
        foreach (AdvertPool::cases() as $pool) {
            Cache::forget(self::cacheKey($pool));
        }
    }

    /**
     * Where a pool's live list is cached.
     */
    public static function cacheKey(AdvertPool $pool): string
    {
        return self::CACHE_KEY.'.'.$pool->value;
    }

    /**
     * Where the visitor's session keeps the advert from this pool they were
     * shown last.
     */
    public function sessionKey(): string
    {
        return self::SESSION_KEY.'.'.$this->pool->value;
    }

    /**
     * The least served of the live adverts, leaving out the one the visitor
     * saw last unless it is the only one, and count the view to it.
     *
     * @param  non-empty-list<array{id: int}>  $live
     * @return array{id: int}
     */
    private function choose(array $live, mixed $lastShown): array
    {
        $prefix = $this->countPrefix($live);
        $served = Cache::many(array_map(fn (array $advert): string => $prefix.$advert['id'], $live));

        $candidates = array_values(array_filter(
            $live,
            fn (array $advert): bool => $advert['id'] !== (int) $lastShown,
        )) ?: $live;

        $chosen = $candidates[0];
        $fewest = PHP_INT_MAX;

        foreach ($candidates as $advert) {
            $count = (int) ($served[$prefix.$advert['id']] ?? 0);

            if ($count < $fewest) {
                $chosen = $advert;
                $fewest = $count;
            }
        }

        // The database cache only increments a count that is already there.
        Cache::add($prefix.$chosen['id'], 0, now()->addDays(self::COUNT_DAYS));
        Cache::increment($prefix.$chosen['id']);

        return $chosen;
    }

    /**
     * The key the counts for this line-up are kept under. A different set of
     * live adverts has different keys, so its counts start from nothing.
     *
     * @param  list<array{id: int}>  $live
     */
    private function countPrefix(array $live): string
    {
        return 'adverts.served.'.$this->pool->value.'.'.md5(implode(',', array_column($live, 'id'))).'.';
    }
}
