<?php

namespace Database\Factories;

use App\Models\Feature;
use App\Models\FeatureRead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureRead>
 */
class FeatureReadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feature_id' => Feature::factory(),
            'read_on' => today(),
            'read_count' => 1,
            'seconds_read' => 120,
            'reached_end_count' => 0,
        ];
    }
}
