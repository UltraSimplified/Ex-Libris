<?php

namespace Database\Factories;

use App\Models\Issue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * month_year is a free-text column that the archives screen filters with a
     * LIKE against the year, so the "F Y" format here matters to those queries.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $published = CarbonImmutable::instance(fake()->dateTimeBetween('-3 years'));

        return [
            'issue_number' => fake()->numberBetween(1, 200),
            'month_year' => $published->format('F Y'),
            'tagline' => fake()->sentence(),
            'cover_photo' => 'issues/'.fake()->uuid().'.webp',
            'hero_image' => 'issues/'.fake()->uuid().'.webp',
            'alt_text' => fake()->sentence(),
            'cover_artist' => fake()->name(),
            'playlist' => fake()->url(),
            'playlist_creator' => fake()->name(),
            'pdf' => 'issues/'.fake()->uuid().'.pdf',
        ];
    }

    /**
     * Publish the issue in a given year, and optionally a given month.
     */
    public function forYear(int $year, ?int $month = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'month_year' => CarbonImmutable::create($year, $month ?? fake()->numberBetween(1, 12), 1)
                ->format('F Y'),
        ]);
    }

    /**
     * Give the issue a specific issue number, which drives archive ordering.
     */
    public function numbered(int $number): static
    {
        return $this->state(fn (array $attributes): array => [
            'issue_number' => $number,
        ]);
    }
}
