<?php

namespace App\Models;

use Database\Factories\ShopSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The shop-wide values the office changes in the Office: the most an order
 * pays for postage, and where and when collection orders are picked up.
 *
 * There is only ever the one row, made by a migration.
 */
class ShopSettings extends Model
{
    /** @use HasFactory<ShopSettingsFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'shop_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'postage_cap',
        'collection_name',
        'collection_address',
        'collection_hours',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'postage_cap' => 'integer',
        ];
    }

    /**
     * The shop's settings.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->firstOrFail();
    }

    /**
     * The collection address a line at a time.
     *
     * @return list<string>
     */
    public function collectionAddressLines(): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $this->collection_address)),
            fn (string $line): bool => $line !== '',
        ));
    }
}
