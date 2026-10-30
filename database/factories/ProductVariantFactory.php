<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state: a standard variant, with neither
     * colour nor size, and no limit on stock.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'colour' => null,
            'colour_hex' => null,
            'size' => null,
            'sku' => null,
            'price' => 2500,
            'stock' => null,
            'position' => 0,
        ];
    }

    /**
     * A shirt in a colour and size.
     */
    public function shirt(string $colour, string $size, string $hex = '#000000'): static
    {
        return $this->state(fn (array $attributes) => [
            'colour' => $colour,
            'colour_hex' => $hex,
            'size' => $size,
            'position' => ProductVariant::sizeRank($size),
        ]);
    }

    /**
     * None left.
     */
    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }
}
