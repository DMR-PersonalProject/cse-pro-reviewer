<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Audit\Audit;
use App\Enums\QuestionStatus;
use App\Models\Question;
use DomainException;

/**
 * Takes a published item out of new tests (FR-Q2). Past attempts keep their snapshots.
 */
class ArchiveQuestion
{
    /**
     * @throws DomainException when the item isn't published
     */
    public function handle(Question $question): void
    {
        if (! $question->status->canTransitionTo(QuestionStatus::Archived)) {
            throw new DomainException(__('Only published items can be archived.'));
        }

        $question->forceFill([
            'status' => QuestionStatus::Archived,
            'archived_at' => now(),
        ])->save();

        Audit::record('question.archived', $question);
    }
}
