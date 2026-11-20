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
            'needs_delivery' => true,
            'description' => null,
            'position' => 0,
        ];
    }

    /**
     * Like the migrations' No postage band: free, and never posted or
     * collected, as a donation.
     */
    public function noPostage(): static
    {
        return $this->state([
            'name' => 'No postage',
            'amount' => 0,
            'needs_delivery' => false,
        ]);
    }
}
