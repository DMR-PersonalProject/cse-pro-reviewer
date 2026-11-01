<?php

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\AttemptResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A passing v1 result for a submitted 20-item attempt.
 *
 * @extends Factory<AttemptResult>
 */
class AttemptResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => Attempt::factory()->submitted(),
            'formula_version' => 'v1',
            'general_rating' => '85.00',
            'passing_grade' => '80.00',
            'passed' => true,
            'correct_count' => 17,
            'total_count' => 20,
            'time_used_seconds' => 600,
            'end_reason' => AttemptStatus::Submitted,
            'topic_breakdown' => [],
        ];
    }
}
