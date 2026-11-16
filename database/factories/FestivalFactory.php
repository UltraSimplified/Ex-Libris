<?php

namespace Database\Factories;

use App\Models\Festival;
use App\Models\Promoter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Festival>
 */
class FestivalFactory extends Factory
{
    protected $model = Festival::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promoter_id' => Promoter::factory(),
            'name' => fake()->unique()->words(2, true).' Festival',
            'website' => fake()->url(),
        ];
    }
}
