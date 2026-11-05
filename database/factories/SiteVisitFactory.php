<?php

namespace Database\Factories;

use App\Models\SiteVisit;
use Carbon\Carbon;
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

    /**
     * A visit of more than one page, which is the only kind with a length
     * the Office can read.
     */
    public function lasting(int $seconds): static
    {
        return $this->state(function (array $attributes) use ($seconds): array {
            $arrived = Carbon::parse($attributes['visited_at'])->addMinute();

            return [
                'requests' => 2,
                'first_seen_at' => $arrived,
                'last_seen_at' => $arrived->copy()->addSeconds($seconds),
            ];
        });
    }
}
