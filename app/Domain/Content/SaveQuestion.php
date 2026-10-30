<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Models\Question;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Saves an item's own fields and its choices from the admin editor. Status is not touched here.
 * Passage placement (subtest, topic, position) is handled by the Question model.
 */
class SaveQuestion
{
    private const FIELDS = [
        'subtest_id', 'topic_id', 'passage_id', 'source_id', 'stem', 'explanation', 'difficulty',
        'language', 'lock_choice_order', 'source_file', 'source_page', 'source_notes',
    ];

    /**
     * @param  array<string, mixed>  $data  form state; "choices" is a list of {body, is_correct}
     */
    public function handle(?Question $question, array $data): Question
    {
        $question ??= new Question;

        return DB::transaction(function () use ($question, $data) {
            $question->fill(Arr::only($data, self::FIELDS));
            $question->save();

            if (array_key_exists('choices', $data)) {
                $this->replaceChoices($question, array_values($data['choices'] ?? []));
            }

            return $question;
        });
    }

    /**
     * Choices have no identity of their own (attempts snapshot them), so they are replaced as a
     * set. This also avoids clashes on the unique (question_id, position) index when reordering.
     *
     * @param  list<array{body: string, is_correct?: bool}>  $choices
     */
    private function replaceChoices(Question $question, array $choices): void
    {
        $question->choices()->delete();

        foreach ($choices as $index => $choice) {
            $question->choices()->create([
                'position' => $index + 1,
                'body' => trim((string) $choice['body']),
                'is_correct' => (bool) ($choice['is_correct'] ?? false),
            ]);
        }

        $question->unsetRelation('choices');
    }
}
