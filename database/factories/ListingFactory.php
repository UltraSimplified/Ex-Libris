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

    /**
     * On the given days only, as "2026-11-03", its run reaching from the
     * first of them to the last, as saving one from the Dashboard leaves it.
     */
    public function onDays(string ...$days): static
    {
        sort($days);

        return $this
            ->state([
                'start_date' => $days[0],
                'end_date' => $days[array_key_last($days)],
            ])
            ->afterCreating(function (Listing $listing) use ($days): void {
                $listing->days()->createMany(
                    array_map(fn (string $day): array => ['day' => $day], $days)
                );
            });
    }
}
