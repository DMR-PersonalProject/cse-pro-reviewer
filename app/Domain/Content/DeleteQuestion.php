<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Audit\Audit;
use App\Models\Choice;
use App\Models\Media;
use App\Models\Question;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hard delete for a takedown (FR-Q5): the admin must type the item ID. Choices, tag links and
 * media rows go with it. Image files stay on disk; the snapshot-aware cleanup and the purge
 * option arrive in M4-17.
 */
class DeleteQuestion
{
    /**
     * @throws DomainException when the confirmation doesn't match the ID
     */
    public function handle(Question $question, string $confirmation): void
    {
        if (trim($confirmation) !== (string) $question->id) {
            throw new DomainException(__('Type the item ID to confirm.'));
        }

        DB::transaction(function () use ($question) {
            $this->remove($question);

            Audit::record('question.deleted', $question, meta: [
                'stem' => Str::limit($question->stem, 200),
                'source_id' => $question->source_id,
                'status' => $question->status->value,
                'purged' => false,
            ]);
        });
    }

    /**
     * Deletes the item and everything that hangs off it, without auditing (callers audit).
     */
    public function remove(Question $question): void
    {
        $choiceIds = $question->choices()->pluck('id');

        Media::query()
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('mediable_type', $question->getMorphClass())->where('mediable_id', $question->id))
                ->orWhere(fn ($q) => $q->where('mediable_type', (new Choice)->getMorphClass())->whereIn('mediable_id', $choiceIds)))
            ->delete();

        $question->delete();
    }
}
