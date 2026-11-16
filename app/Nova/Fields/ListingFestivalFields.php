<?php

namespace App\Nova\Fields;

use App\Nova\Festival;
use App\Nova\Promoter;
use App\Rules\FestivalOfPromoter;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Which festival a listing is in, as the Office edits it: one of its own
 * promoter's, offered as the promoter is chosen, and whether the festival's
 * mark is drawn on its cover.
 *
 * Staff may choose any of that promoter's festivals, so the rule asks only
 * that the festival is the promoter's. Choosing another promoter in
 * Backstage or here takes the listing out of a festival that is not the
 * new one's; the listing sees to that as it saves.
 */
final class ListingFestivalFields
{
    /**
     * @return array<int, Field>
     */
    public function fields(): array
    {
        return [
            BelongsTo::make('Festival', 'festival', Festival::class)
                ->nullable()
                ->hideFromIndex()
                ->rules('nullable', new FestivalOfPromoter(promoterField: 'promoter'))
                ->dependsOn(['promoter'], function (BelongsTo $field, NovaRequest $request, FormData $formData): void {
                    $promoterId = (int) $formData->resource(Promoter::uriKey(), $formData->get('promoter'));

                    $field->relatableQueryUsing(
                        fn (NovaRequest $request, Builder $query): Builder => $query->where('promoter_id', $promoterId),
                    );
                })
                ->help('One of the promoter\'s festivals, which links the event to the festival\'s page.'),
            Boolean::make('Show the festival mark on the cover', 'show_festival_mark')
                ->default(true)
                ->hideFromIndex()
                ->help('The festival\'s logo in the bottom right of the cover, wherever the event is listed. Untick it if it would hide something on the poster.'),
        ];
    }
}
