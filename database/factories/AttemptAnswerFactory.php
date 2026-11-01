<?php

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An unanswered item snapshotted from a fresh question; the correct answer is A.
 *
 * @extends Factory<AttemptAnswer>
 */
class AttemptAnswerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => Attempt::factory(),
            'question_id' => Question::factory(),
            'position' => 1,
            'subtest_id' => fn (array $attributes) => Question::find($attributes['question_id'])->subtest_id,
            'topic_id' => fn (array $attributes) => Question::find($attributes['question_id'])->topic_id,
            'snapshot' => fn (array $attributes) => [
                'stem' => Question::find($attributes['question_id'])->stem,
                'passage' => null,
                'choices' => [
                    ['key' => 'A', 'body' => 'First', 'media' => []],
                    ['key' => 'B', 'body' => 'Second', 'media' => []],
                    ['key' => 'C', 'body' => 'Third', 'media' => []],
                    ['key' => 'D', 'body' => 'Fourth', 'media' => []],
                ],
                'explanation' => null,
                'source_visibility' => 'private',
            ],
            'correct_key' => 'A',
        ];
    }
}
