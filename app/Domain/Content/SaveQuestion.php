<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Models\Passage;
use App\Models\Question;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Saves an item's own fields and its choices from the admin editor. Status is not touched here.
 * An item in a passage takes the passage's subtest and topic (FR-Q3).
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
            $this->placeInPassage($question);
            $question->save();

            if (array_key_exists('choices', $data)) {
                $this->replaceChoices($question, array_values($data['choices'] ?? []));
            }

            return $question;
        });
    }

    private function placeInPassage(Question $question): void
    {
        if (! $question->passage_id) {
            $question->passage_position = null;

            return;
        }

        $passage = Passage::findOrFail($question->passage_id);
        $question->subtest_id = $passage->subtest_id;
        $question->topic_id = $passage->topic_id;

        if (! $question->exists || $question->isDirty('passage_id') || $question->passage_position === null) {
            $question->passage_position = ($passage->questions()->max('passage_position') ?? 0) + 1;
        }
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
