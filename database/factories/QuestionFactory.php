<?php

namespace Database\Factories;

use App\Enums\QuestionStatus;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Source;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft without choices by default; use withChoices() or published() for a complete item.
 *
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
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
            'stem' => fake()->sentence(12).'?',
            'explanation' => fake()->sentence(),
            'status' => QuestionStatus::Draft,
        ];
    }

    /**
     * Adds choices at positions 1..$count; $correct is the 1-based position of the right one.
     */
    public function withChoices(int $count = 4, int $correct = 1): static
    {
        return $this->afterCreating(function (Question $question) use ($count, $correct) {
            foreach (range(1, $count) as $position) {
                $question->choices()->create([
                    'position' => $position,
                    'body' => fake()->words(3, true),
                    'is_correct' => $position === $correct,
                ]);
            }
        });
    }

    public function published(): static
    {
        return $this->withChoices()->state(fn (array $attributes) => [
            'status' => QuestionStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => ['source_id' => Source::factory()]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => ['source_id' => Source::factory()->public()]);
    }

    /**
     * Places the item in a passage; it takes the passage's subtest and topic (FR-Q3).
     */
    public function inPassage(?Passage $passage = null, ?int $position = null): static
    {
        return $this->state(function (array $attributes) use ($passage, $position) {
            $passage ??= Passage::factory()->create();

            return [
                'passage_id' => $passage->id,
                'subtest_id' => $passage->subtest_id,
                'topic_id' => $passage->topic_id,
                'passage_position' => $position ?? ($passage->questions()->max('passage_position') ?? 0) + 1,
            ];
        });
    }
}
