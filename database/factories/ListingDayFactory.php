<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingDay>
 */
class ListingDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'day' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
        ];
    }
}
