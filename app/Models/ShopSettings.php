<?php

namespace App\Models;

use App\Services\Geocoding\PostcodeGeocoder;
use Database\Factories\ShopSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The shop-wide values the office changes in the Office: the most an order
 * pays for postage, where and when collection orders are picked up, and how
 * the shop page describes itself to search engines and link previews.
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
        'latitude',
        'longitude',
        'meta_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'postage_cap' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Put the collection address on the map when it is set or changed, or
     * when there is an address but no place yet.
     *
     * A place set by hand in the Office is kept, as a venue's is: a save
     * that changes the latitude or longitude itself is not looked up again,
     * so the pin can be nudged off the middle of the postcode and onto the
     * door and stay there.
     */
    protected static function booted(): void
    {
        static::saving(function (self $settings): void {
            if ($settings->isDirty(['latitude', 'longitude'])) {
                return;
            }

            $unplaced = $settings->latitude === null || $settings->longitude === null;

            if (! $settings->isDirty('collection_address') && ! $unplaced) {
                return;
            }

            $place = app(PostcodeGeocoder::class)->locate($settings->collectionPostcode());

            $settings->latitude = $place['latitude'] ?? null;
            $settings->longitude = $place['longitude'] ?? null;
        });
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

    /**
     * The postcode out of the collection address, which is free text so the
     * office can lay the address out however it reads best. The last one
     * wins, since a postcode comes at the end of an address.
     */
    public function collectionPostcode(): ?string
    {
        preg_match_all(
            '/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/i',
            (string) $this->collection_address,
            $matches,
        );

        return $matches[0] === [] ? null : (string) end($matches[0]);
    }
}
