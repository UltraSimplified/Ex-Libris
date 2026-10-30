<?php

namespace Database\Factories;

use App\Models\Promoter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * An account the office has marked as writing for the magazine, with
     * the byline a feature is credited to.
     */
    public function writer(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_writer' => true,
            'bio' => fake()->paragraph(),
            'avatar' => 'avatars/'.fake()->uuid().'.webp',
            'avatar_alt_text' => fake()->sentence(),
        ]);
    }

    /**
     * An account the office has agreed and linked to a promoter, which is
     * what it takes to add and edit listings from the dashboard.
     */
    public function agreed(): static
    {
        return $this->state(fn (array $attributes) => ['is_promoter' => true])
            ->afterCreating(fn (User $user) => Promoter::factory()->create(['user_id' => $user->id]));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
