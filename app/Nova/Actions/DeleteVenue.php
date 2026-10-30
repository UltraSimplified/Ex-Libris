<?php

namespace App\Nova\Actions;

use App\Models\Venue;
use App\Services\Widgets\WidgetCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Jobs\DeletesFields;

/**
 * Delete venues that still have listings, which the ordinary delete is not
 * offered for since the listings would be left pointing at nothing.
 *
 * Their listings, and their gig widgets so the snippet on the venue's site
 * keeps working, go to the venue chosen, which is how a duplicate is folded
 * into the real one. With none chosen the listings stay up as "Venue to be
 * confirmed" and the widgets go with the venue.
 */
class DeleteVenue extends DestructiveAction
{
    use DeletesFields;

    public $name = 'Delete Venue';

    public $confirmButtonText = 'Delete Venue';

    public $confirmText = 'Delete the selected venue? Its listings move to the venue chosen below, or are left as "Venue to be confirmed" if none is chosen. This cannot be undone.';

    /**
     * @param  Collection<int, Venue>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $target = $fields->get('move_to') ? Venue::find($fields->get('move_to')) : null;

        if ($target && $models->contains($target)) {
            return Action::danger("Its listings can't move to '{$target->name}': that venue is being deleted too.");
        }

        $request = NovaRequest::createFrom(request());
        $moved = 0;

        foreach ($models as $venue) {
            DB::transaction(function () use ($venue, $target, $request, &$moved) {
                $moved += $venue->listings()->update(['venue_id' => $target?->id]);

                if ($target) {
                    $venue->widgets()->update(['owner_id' => $target->id]);
                } else {
                    $venue->widgets()->delete();
                }

                $this->deleteFields($request, $venue);

                $venue->delete();
            });
        }

        if ($target) {
            app(WidgetCache::class)->forgetOwner(Venue::class, $target->id);
        }

        $deleted = $models->count() === 1 ? "'{$models->first()->name}'" : $models->count().' venues';
        $listings = $moved === 1 ? '1 listing' : "{$moved} listings";

        return Action::message($target
            ? "Deleted {$deleted} and moved {$listings} to '{$target->name}'."
            : "Deleted {$deleted}; {$listings} now show \"Venue to be confirmed\".");
    }

    /**
     * @return array<int, Select>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Select::make('Move its listings to', 'move_to')
                ->options(fn () => Venue::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->nullable()
                ->help('Leave empty to keep its listings up as "Venue to be confirmed".'),
        ];
    }
}
