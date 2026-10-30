<?php

namespace Database\Factories;

use App\Models\ShopSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopSettings>
 */
class ShopSettingsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'postage_cap' => 1000,
            'collection_name' => 'Good on Paper, Centre for Science & Art',
            'collection_address' => "Lansdown\nStroud\nGL5 1BB",
            'collection_hours' => null,
        ];
    }
}
