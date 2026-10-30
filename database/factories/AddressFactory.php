<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => Address::BILLING,
            'line_one' => fake()->streetAddress(),
            'line_two' => null,
            'town' => fake()->city(),
            // Faker runs in en_US here, which has no counties.
            'county' => fake()->randomElement(['Gloucestershire', 'Wiltshire', 'Somerset']),
            'postcode' => fake()->postcode(),
            'country' => 'United Kingdom',
        ];
    }

    /**
     * Somewhere to send the magazine, rather than the bill.
     */
    public function delivery(): static
    {
        return $this->state(fn (): array => ['type' => Address::DELIVERY]);
    }
}
