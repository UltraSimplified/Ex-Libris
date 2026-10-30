<?php

namespace Database\Factories;

use App\Models\PostageBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostageBand>
 */
class PostageBandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Letter', 'Large letter', 'Parcel', 'Tube']),
            'amount' => fake()->randomElement([200, 300, 400, 600]),
            'description' => null,
            'position' => 0,
        ];
    }
}
