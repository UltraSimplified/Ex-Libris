<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

// App\Nova\AdvertEvent — read-only, exists so the HasMany renders nicely
/**
 * @extends resource<\App\Models\AdvertEvent>
 */
class AdvertEvent extends Resource
{
    public static $model = \App\Models\AdvertEvent::class;

    public static $displayInNavigation = false;

    public static function authorizedToCreate(Request $request): false
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): false
    {
        return false;
    }

    public function authorizedToDelete(Request $request): false
    {
        return false;
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Badge::make('Type')->map([
                'impression' => 'info',
                'click' => 'success',
            ]),
            Text::make('Page'),
            Text::make('Variant'),
            Text::make('Locality', 'locality_bucket'),
            Text::make('City'),
            DateTime::make('Created At')->sortable(),
        ];
    }
}
