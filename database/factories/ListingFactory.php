<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            // Stated rather than left to the column default, so a listing
            // made here answers the question without being refetched.
            'sold_out' => false,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'button_text' => 'Find out more',
            'category_id' => Category::factory(),
            'venue_id' => Venue::factory(),
            'start_date' => fake()->dateTimeBetween('now', '+2 months'),
            'end_date' => null,
        ];
    }
}
