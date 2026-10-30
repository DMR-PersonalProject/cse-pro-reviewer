<?php

namespace Database\Factories;

use App\Enums\Role;
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
     * Define the model's default state: a user without a PIN or email.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => 'user_'.fake()->unique()->numerify('#######'),
            'ign' => 'Player '.fake()->unique()->numerify('######'),
            'full_name' => fake()->name(),
            'email' => null,
            'password' => null,
            'role' => Role::User,
            'is_active' => true,
            'pin_set_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An admin, who signs in with email and password.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Admin,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * A user who has set a PIN.
     */
    public function withPin(string $pin = '1234'): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => Hash::make($pin),
            'pin_set_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
