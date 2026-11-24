<?php

namespace App\Nova\Cards;

use App\Models\Feature;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Card;

/**
 * The Office's headline counts in one tile: venues, then events, then
 * writers, each with the figures that go with it.
 *
 * Replaces a tile for each, where the same counts sat over a top-five list.
 * The lists went and the counts were gathered up here.
 */
class HeadlineCounts extends Card
{
    public $width = '1/2';

    public function component(): string
    {
        return 'gop-headline-counts';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'heading' => 'Statistics',
            'sections' => collect([
                'Venues' => $this->venues(),
                'Events' => $this->events(),
                'Writers' => $this->writers(),
            ])
                ->map(fn (array $counts, string $heading): array => [
                    'heading' => $heading,
                    'counts' => collect($counts)
                        ->map(fn (int $value, string $label): array => ['label' => $label, 'value' => $value])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Every venue on the books, and every event still to come or running
     * today, wherever it is on.
     *
     * @return array<string, int>
     */
    private function venues(): array
    {
        return [
            'Venues' => Venue::count(),
            'Upcoming events' => Listing::query()->current()->count(),
        ];
    }

    /**
     * Every promoter on the books, busy or not, and everything ever listed,
     * promoted or not, finished or not.
     *
     * Bookmarks are every save rather than every listing saved: one listing
     * that fifty people kept is fifty readings of it, and engagement is what
     * this figure is here to show.
     *
     * @return array<string, int>
     */
    private function events(): array
    {
        return [
            'Promoters' => Promoter::count(),
            'Events listed' => Listing::count(),
            'Bookmarks' => DB::table('bookmarks')->count(),
        ];
    }

    /**
     * The accounts marked as writing, rather than the writer records they
     * were made from, and everything ever published. The column rather than
     * hasRole(): staff hold every role without being marked with any, and
     * the count of writers should not be padded out with the office.
     *
     * @return array<string, int>
     */
    private function writers(): array
    {
        return [
            'Writers' => User::writers()->count(),
            'Articles' => Feature::count(),
        ];
    }
}
