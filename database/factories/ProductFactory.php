<?php

namespace Database\Factories;

use App\Models\PostageBand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state: published, on a published shelf,
     * with a postage band. It has no variant until one is given, so on its
     * own it is not on sale - see onSale().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'shop_category_id' => ShopCategory::factory(),
            'postage_band_id' => PostageBand::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'image' => 'shop/'.Str::slug($name).'.webp',
            'position' => 0,
            'is_published' => true,
        ];
    }

    /**
     * On sale, with a single standard variant at the given price in pence.
     */
    public function onSale(int $price = 2500, ?int $stock = null): static
    {
        return $this->has(
            ProductVariant::factory()->state(['price' => $price, 'stock' => $stock]),
            'variants',
        );
    }

    /**
     * Not yet published.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
