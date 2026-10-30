<?php

namespace App\Nova\Actions;

use App\Models\Advert;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Switch the chosen adverts on. Any number can be live together, and the
 * site shares its page views between them, so the others are left as they
 * are. An advert still only serves within its run dates.
 */
class MakeLive extends Action
{
    public $name = 'Make Live';

    public $confirmText = 'Make the selected adverts live? Any other live adverts stay live and share the page views.';

    /**
     * @param  Collection<int, Advert>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        // Set directly: the model only lets type and location be mass assigned.
        $models->each(fn (Advert $advert) => $advert->forceFill(['active' => true])->save());

        return Action::message($models->count() === 1
            ? "'{$models->first()->title}' is now live."
            : $models->count().' adverts are now live.');
    }
}
