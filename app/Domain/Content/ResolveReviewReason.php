<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Audit\Audit;
use App\Enums\NeedsReviewReason;
use App\Enums\QuestionStatus;
use App\Models\Question;

/**
 * An admin marks one review reason as dealt with. When none are left, the item goes back to
 * draft (FR-Q2: needs_review ↔ draft), from where it can be published.
 */
class ResolveReviewReason
{
    public function handle(Question $question, NeedsReviewReason $reason): void
    {
        if (! $question->needs_review_reasons->contains($reason)) {
            return;
        }

        $remaining = $question->needs_review_reasons->reject(fn (NeedsReviewReason $item) => $item === $reason)->values();

        $question->needs_review_reasons = $remaining;

        if ($remaining->isEmpty() && $question->status === QuestionStatus::NeedsReview) {
            $question->status = QuestionStatus::Draft;
        }

        $question->save();

        Audit::record('question.review_resolved', $question, meta: [
            'reason' => $reason->value,
            'remaining' => $remaining->map(fn (NeedsReviewReason $item) => $item->value)->all(),
        ]);
    }
}
