<?php

namespace Database\Factories;

use App\Enums\AttemptMode;
use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\Subtest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A timed 20-item individual attempt in progress, without answer rows.
 *
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mode' => AttemptMode::Individual,
            'timing' => AttemptTiming::Timed,
            'subtest_id' => Subtest::factory(),
            'item_count' => 20,
            'config' => ['minutes' => 23, 'passing_grade' => 80.0, 'grace_seconds' => 10, 'suspicious_seconds_per_item' => 5],
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'deadline_at' => now()->addMinutes(23),
            'seed' => fake()->numberBetween(1, 2_000_000_000),
        ];
    }

    public function untimed(): static
    {
        return $this->state(fn (array $attributes) => ['timing' => AttemptTiming::Untimed, 'deadline_at' => null]);
    }

    public function study(): static
    {
        return $this->state(fn (array $attributes) => ['timing' => AttemptTiming::Study, 'deadline_at' => null]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttemptStatus::Submitted,
            'finalized_at' => now(),
            'time_used_seconds' => 600,
        ]);
    }
}
