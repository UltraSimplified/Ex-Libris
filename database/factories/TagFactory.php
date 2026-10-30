<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tag' => ucfirst(fake()->word()),
        ];
    }

    /**
     * The "Reviews" tag.
     *
     * The front end identifies reviews by the hard-coded id 5 (see routes/web.php),
     * so this state pins the id rather than letting it auto-increment. Create it
     * once per test — a second call will collide on the primary key.
     */
    public function reviews(): static
    {
        return $this->state(fn (array $attributes): array => [
            'id' => FeatureFactory::REVIEWS_TAG_ID,
            'tag' => 'Reviews',
        ]);
    }
}
