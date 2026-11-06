<?php

namespace Database\Factories;

use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Models\Widget;
use App\Support\Widgets\WidgetDensity;
use App\Support\Widgets\WidgetSizeMode;
use App\Support\Widgets\WidgetTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Widget>
 */
class WidgetFactory extends Factory
{
    /**
     * A detailed list of a venue's events.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => Venue::class,
            'owner_id' => Venue::factory(),
            'name' => 'Home page',
            'template' => WidgetTemplate::List,
            'density' => WidgetDensity::Detailed,
            'size_mode' => WidgetSizeMode::Auto,
            'active' => true,
        ];
    }

    /**
     * Showing a promoter's events rather than a venue's.
     */
    public function forPromoter(?Promoter $promoter = null): static
    {
        return $this->state(fn (): array => [
            'owner_type' => Promoter::class,
            'owner_id' => $promoter?->id ?? Promoter::factory(),
        ]);
    }

    /**
     * Showing the events a publisher has bookmarked.
     */
    public function forBookmarks(?User $publisher = null): static
    {
        return $this->state(fn (): array => [
            'owner_type' => User::class,
            'owner_id' => $publisher?->id ?? User::factory()->publisher(),
        ]);
    }

    /**
     * Switched off by its owner.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
