<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name' => ucfirst(fake()->words(3, true)),
            'variant_name' => 'Standard',
            'unit_price' => 2500,
            'quantity' => 1,
            'postage_band' => 'Large letter',
            'unit_postage' => 300,
        ];
    }
}
