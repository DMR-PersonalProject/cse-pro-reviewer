<?php

namespace Database\Factories;

use App\Enums\QuestionStatus;
use App\Models\Passage;
use App\Models\Source;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Passage>
 */
class PassageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'subtest_id' => fn (array $attributes) => Topic::find($attributes['topic_id'])->subtest_id,
            'source_id' => Source::factory(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraphs(2, true),
            'status' => QuestionStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => QuestionStatus::Published]);
    }
}
