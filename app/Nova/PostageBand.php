<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * The four postage bands. A migration makes them and the Office only sets
 * their names and prices: a fifth band, or one fewer, would leave products
 * with nowhere to post from.
 */
class PostageBand extends Resource
{
    /**
     * @var class-string<\App\Models\PostageBand>
     */
    public static $model = \App\Models\PostageBand::class;

    /**
     * @var string
     */
    public static $title = 'name';

    /**
     * @var array
     */
    public static $search = [
        'name',
    ];

    /**
     * @var array<string, string>
     */
    public static $sort = [
        'position' => 'asc',
    ];

    public static function label(): string
    {
        return 'Postage bands';
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
            Text::make('Name')->sortable()->rules('required', 'max:60'),
            Currency::make('Amount')
                ->currency('GBP')
                ->asMinorUnits()
                ->rules('required', 'integer', 'min:0')
                ->help('Charged for each item in this band, until an order reaches the postage cap in Shop settings.'),
            Text::make('Description')
                ->nullable()
                ->hideFromIndex()
                ->help('For the office: what goes in this band, and how it is packed.'),
            Number::make('Position')->min(0)->step(1)->rules('required', 'integer', 'min:0')->hideFromIndex(),
            HasMany::make('Products', 'products', Product::class),
        ];
    }
}
