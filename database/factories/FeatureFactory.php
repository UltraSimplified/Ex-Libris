<?php

namespace Database\Factories;

use App\Models\Feature;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    /**
     * The tag id the front end treats as a review rather than a feature.
     *
     * Hard-coded as the literal 5 in routes/web.php and IssueController.
     */
    public const REVIEWS_TAG_ID = 5;

    /**
     * Define the model's default state.
     *
     * The slug is deliberately omitted so that the model's saving hook generates
     * it, which is the behaviour production relies on when Nova creates a record.
     *
     * Note: tag_id gets a fresh Tag, whose auto-increment id could in principle
     * land on REVIEWS_TAG_ID. Tests that care about the feature/review split
     * should set tag_id explicitly rather than relying on the default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_number' => fake()->numberBetween(1, 60),
            'title' => rtrim(fake()->sentence(4), '.'),
            'subject' => fake()->words(3, true),
            'published' => fake()->dateTimeBetween('-2 years')->format('Y-m-d'),
            'colourway' => fake()->randomElement(['white', 'hi-vis', 'slate', 'key-lime']),
            'tag_id' => Tag::factory(),
            'cover_photo' => 'features/'.fake()->uuid().'.webp',
            'cover_alt_text' => fake()->sentence(),
            'feature_image' => 'features/'.fake()->uuid().'.webp',
            'feature_alt_text' => fake()->sentence(),
            'body' => json_encode([
                [
                    'layout' => 'copy',
                    'key' => fake()->uuid(),
                    'attributes' => [
                        'text' => '<p>'.fake()->paragraph().'</p>',
                    ],
                ],
            ]),
            'excerpt' => fake()->paragraph(),
            'listing_id' => null,
            'user_id' => User::factory()->writer(),
        ];
    }

    /**
     * A feature the front end classifies as a review.
     *
     * Sets the foreign key only — create the matching Tag row with
     * Tag::factory()->reviews() if the test needs the relation to resolve.
     */
    public function review(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tag_id' => self::REVIEWS_TAG_ID,
        ]);
    }

    /**
     * A feature nobody is credited with. The byline is nullable, and the
     * front end has to cope with an article that has lost its writer.
     */
    public function unbylined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }

    /**
     * A feature with no tag, which the front end excludes from every listing.
     */
    public function untagged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tag_id' => null,
        ]);
    }

    /**
     * A feature picked for the top of the home page.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'featured' => true,
        ]);
    }

    /**
     * Use an explicit slug instead of letting the model generate one.
     */
    public function withSlug(string $slug): static
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => $slug,
        ]);
    }
}
