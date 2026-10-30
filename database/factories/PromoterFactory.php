<?php

namespace Database\Factories;

use App\Models\Promoter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promoter>
 */
class PromoterFactory extends Factory
{
    protected $model = Promoter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'website' => fake()->url(),
            'email' => fake()->safeEmail(),
        ];
    }
}
