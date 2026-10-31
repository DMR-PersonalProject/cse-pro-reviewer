<?php

declare(strict_types=1);

namespace App\Domain\Assembly;

use App\Enums\SourceVisibility;
use App\Models\Choice;
use App\Models\Media;
use App\Models\Question;
use Illuminate\Support\Collection;
use LogicException;
use Random\Randomizer;

/**
 * Freezes a question as the attempt shows it (plan §3.2), so later edits and deletes never
 * change a result or review.
 */
final class SnapshotBuilder
{
    public function __construct(private readonly ChoiceShuffler $shuffler) {}

    /**
     * @return array{snapshot: array<string, mixed>, correct_key: string}
     */
    public function build(Question $question, Randomizer $randomizer): array
    {
        $question->loadMissing(['choices.media', 'media', 'source', 'passage.media', 'passage.source']);

        $choices = [];
        $correctKey = null;

        foreach ($this->shuffler->shuffle($question, $randomizer) as $index => $choice) {
            $key = chr(ord('A') + $index);
            $correctKey = $choice->is_correct ? $key : $correctKey;
            $choices[] = ['key' => $key, 'body' => $choice->body, 'media' => $this->media($choice->media)];
        }

        // Publishing requires exactly one correct choice, so this means the data is broken.
        if ($correctKey === null) {
            throw new LogicException("Question {$question->id} has no correct choice.");
        }

        $passage = $question->passage;

        return [
            'snapshot' => [
                'stem' => $question->stem,
                'media' => $this->media($question->media),
                'passage' => $passage ? ['body' => $passage->body, 'media' => $this->media($passage->media)] : null,
                'choices' => $choices,
                'explanation' => $question->explanation,
                'source_visibility' => $this->visibility($question)->value,
                'question_updated_at' => $question->updated_at->toIso8601String(),
            ],
            'correct_key' => $correctKey,
        ];
    }

    /**
     * Private when the question's or its passage's source is private.
     */
    private function visibility(Question $question): SourceVisibility
    {
        $private = $question->source->visibility === SourceVisibility::Private
            || $question->passage?->source->visibility === SourceVisibility::Private;

        return $private ? SourceVisibility::Private : SourceVisibility::Public;
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return list<array<string, mixed>>
     */
    private function media(Collection $media): array
    {
        return $media->sortBy('position')->map(fn (Media $item) => [
            'disk' => $item->disk,
            'path' => $item->path,
            'mime' => $item->mime,
            'width' => $item->width,
            'height' => $item->height,
            'alt_text' => $item->alt_text,
        ])->values()->all();
    }
}
