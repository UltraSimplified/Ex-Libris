<?php

namespace Database\Factories;

use App\Models\Order;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state: a guest's posted order, sent to
     * Stripe and not yet paid.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'email' => null,
            'status' => OrderStatus::Pending,
            'fulfilment' => Fulfilment::Post,
            'subtotal' => 2500,
            'shipping' => 300,
            'total' => 2800,
            'postage_capped' => false,
            'currency' => 'gbp',
            'stripe_checkout_session_id' => 'cs_test_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
        ];
    }

    /**
     * Paid for, by a named customer.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'email' => fake()->safeEmail(),
            'name' => fake()->name(),
            'stripe_payment_intent_id' => 'pi_test_'.fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'paid_at' => now(),
        ]);
    }

    /**
     * To be collected rather than posted.
     */
    public function collection(): static
    {
        return $this->state(fn (array $attributes) => [
            'fulfilment' => Fulfilment::Collection,
            'shipping' => 0,
            'total' => $attributes['subtotal'],
        ]);
    }
}
