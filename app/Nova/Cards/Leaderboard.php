<?php

namespace App\Nova\Cards;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Card;

/**
 * A list of whoever has the most on, over a resource's own index.
 *
 * Shared by the promoters, venues and writers lists, which differ only in
 * what they are ranking. Their headline counts are on the Office dashboard,
 * in the headline counts card, so the list here stands on its own.
 */
abstract class Leaderboard extends Card
{
    public $width = '1/2';

    /**
     * How many rows the list runs to.
     */
    protected const LEADERS = 5;

    /**
     * The heading, and the wording used when nothing is running.
     */
    abstract protected function heading(): string;

    abstract protected function emptyText(): string;

    /**
     * A fresh query for the thing being ranked.
     */
    abstract protected function leaderQuery(): Builder;

    /**
     * What the ranking counts, and what one of them is called. Venues and
     * promoters are ranked on events still to come; writers on everything
     * they have ever had published, which has no "upcoming" about it.
     */
    protected function relation(): string
    {
        return 'listings';
    }

    protected function constrain(Builder $query): Builder
    {
        /** @var Builder<Listing> $query */
        return $query->current();
    }

    protected function unit(): string
    {
        return 'event';
    }

    /**
     * The column a row is named by, used to label it and to break ties.
     */
    protected function nameColumn(): string
    {
        return 'name';
    }

    /**
     * The resource pages each row links into.
     */
    abstract protected function link(): string;

    public function component(): string
    {
        return 'gop-leaderboard';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'heading' => $this->heading(),
            'unit' => $this->unit(),
            'rows' => $this->leaders(),
            'emptyText' => $this->emptyText(),
        ]);
    }

    /**
     * The rows, ranked by how much of the counted thing each one has.
     *
     * @return array<int, array{name: string, count: int, link: string}>
     */
    private function leaders(): array
    {
        $relation = $this->relation();
        $constrain = fn (Builder $query): Builder => $this->constrain($query);
        $name = $this->nameColumn();

        return $this->leaderQuery()
            ->withCount([$relation => $constrain])
            ->whereHas($relation, $constrain)
            ->orderByDesc($relation.'_count')
            ->orderBy($name)
            ->limit(static::LEADERS)
            ->get()
            ->map(fn (Model $model): array => [
                'name' => (string) $model->{$name},
                'count' => (int) $model->{$relation.'_count'},
                'link' => $this->link().'/'.$model->id,
            ])
            ->all();
    }
}
