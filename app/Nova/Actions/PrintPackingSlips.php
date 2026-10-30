<?php

namespace App\Nova\Actions;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * A PDF with a page for each selected order, to pack from and tape to the
 * parcel or the bag on the collection shelf.
 */
class PrintPackingSlips extends Action
{
    public $name = 'Print packing slips';

    public $withoutConfirmation = true;

    /**
     * The file is streamed by a signed route rather than written to disk,
     * as the listings export is, so there is nothing to clean up.
     *
     * @param  Collection<int, Order>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $url = URL::temporarySignedRoute('exports.packing-slips', now()->addMinutes(10), [
            'orders' => $models->pluck('id')->sort()->implode(','),
        ]);

        return ActionResponse::download('packing-slips-'.now()->format('Y-m-d').'.pdf', $url);
    }
}
