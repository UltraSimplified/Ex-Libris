<?php

namespace Database\Factories;

use App\Models\Advert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Advert>
 */
class AdvertFactory extends Factory
{
    protected $model = Advert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'campaign' => fake()->word(),
            'owner' => fake()->company(),
            'link' => fake()->url(),
            'active' => true,
            'run_from' => now()->subMonth(),
            'run_to' => now()->addMonth(),
        ];
    }

    /**
     * A sponsorship, shown in the panel at the foot of every page, with the
     * two creatives the Office requires of one.
     */
    public function sponsorship(): static
    {
        return $this->state(fn (): array => [
            'sponsorship' => true,
            'desktop_image' => 'sponsors/desktop.webp',
            'mobile_image' => 'sponsors/mobile.webp',
        ]);
    }
}
