<?php

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\AttemptSubtestScore;
use App\Models\Subtest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttemptSubtestScore>
 */
class AttemptSubtestScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => Attempt::factory()->submitted(),
            'subtest_id' => Subtest::factory(),
            'correct' => 17,
            'total' => 20,
            'percent' => '85.00',
        ];
    }
}
