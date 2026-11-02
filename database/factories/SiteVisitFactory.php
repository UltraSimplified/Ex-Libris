<?php

namespace Database\Factories;

use App\Models\SiteVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteVisit>
 */
class SiteVisitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visited_at' => now()->startOfHour(),
            'visitor_hash' => hash('sha256', fake()->unique()->uuid()),
        ];
    }
}
