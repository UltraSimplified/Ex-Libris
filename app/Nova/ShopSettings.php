<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The shop's one row of settings, edited and never made or removed.
 *
 * @extends \App\Nova\Resource<\App\Models\ShopSettings>
 */
class ShopSettings extends Resource
{
    /**
     * @var class-string<\App\Models\ShopSettings>
     */
    public static $model = \App\Models\ShopSettings::class;

    /**
     * @var string
     */
    public static $title = 'collection_name';

    /**
     * @var array
     */
    public static $search = [];

    public static function label(): string
    {
        return 'Shop settings';
    }

    public static function singularLabel(): string
    {
        return 'Shop settings';
    }

    public static function uriKey(): string
    {
        return 'shop-settings';
    }

    public static function authorizedToCreate(Request $request): false
    {
        return false;
    }

    public function authorizedToDelete(Request $request): false
    {
        return false;
    }

    public function authorizedToReplicate(Request $request): false
    {
        return false;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Currency::make('Postage cap', 'postage_cap')
                ->currency('GBP')
                ->asMinorUnits()
                ->rules('required', 'integer', 'min:0')
                ->help('The most any posted order pays for postage, however many items are in it. Collection is always free.'),
            Text::make('Collection name', 'collection_name')
                ->rules('required', 'max:255')
                ->help('Who and where to collect from, as customers see it.'),
            Textarea::make('Collection address', 'collection_address')
                ->rows(4)
                ->alwaysShow()
                ->rules('required')
                ->help('One line per line of the address.'),
            Textarea::make('Collection hours', 'collection_hours')
                ->rows(4)
                ->alwaysShow()
                ->nullable()
                ->help('When someone is there to hand orders over. Shown on the basket and in the ready-for-collection email.'),
            Text::make('Shop page description', fn (): string => Str::limit((string) $this->meta_description, 60))
                ->onlyOnIndex(),
            Textarea::make('Shop page description', 'meta_description')
                ->rows(3)
                ->alwaysShow()
                ->nullable()
                ->rules('nullable', 'max:300')
                ->help('What the shop page says about itself in search results and when a link to it is shared. Around 155 characters shows in full. Left empty, the site\'s own description is used.'),
        ];
    }
}
