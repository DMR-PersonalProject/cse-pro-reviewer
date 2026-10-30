<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Audit\Audit;
use App\Domain\Content\Exceptions\QuestionNotPublishable;
use App\Enums\NeedsReviewReason;
use App\Enums\QuestionStatus;
use App\Models\Media;
use App\Models\Question;

/**
 * Publishes an item if FR-Q2 allows it (from draft or archived) and every content gate passes.
 */
class PublishQuestion
{
    /**
     * @throws QuestionNotPublishable listing every blocker
     */
    public function handle(Question $question, string $auditAction = 'question.published'): void
    {
        $blockers = $this->blockers($question);

        if ($blockers !== []) {
            throw new QuestionNotPublishable($blockers);
        }

        $from = $question->status;
        $question->forceFill([
            'status' => QuestionStatus::Published,
            'published_at' => now(),
            'archived_at' => null,
        ])->save();

        Audit::record($auditAction, $question, meta: ['from' => $from->value]);
    }

    /**
     * Everything that stops the item from being published; empty when it can be.
     *
     * @return list<string>
     */
    public function blockers(Question $question): array
    {
        $question->loadMissing(['choices.media', 'media', 'passage.media']);
        $blockers = [];

        if ($question->status === QuestionStatus::NeedsReview && $question->needs_review_reasons->isNotEmpty()) {
            foreach ($question->needs_review_reasons as $reason) {
                /** @var NeedsReviewReason $reason */
                $blockers[] = $reason->label();
            }
        } elseif (! $question->status->canTransitionTo(QuestionStatus::Published)) {
            $blockers[] = __('Only draft or archived items can be published.');
        }

        $choiceCount = $question->choices->count();
        $correctCount = $question->choices->where('is_correct', true)->count();

        if ($choiceCount < 2 || $choiceCount > 5) {
            $blockers[] = __('An item needs 2–5 choices.');
        }

        if ($correctCount === 0) {
            $blockers[] = __('No correct choice.');
        } elseif ($correctCount > 1) {
            $blockers[] = __('More than one correct choice.');
        }

        $media = $question->media
            ->concat($question->choices->flatMap->media)
            ->concat($question->passage?->media ?? []);

        if ($media->contains(fn (Media $item) => ! $item->hasAltText())) {
            $blockers[] = __('An image has no alt text.');
        }

        $passage = $question->passage;

        if ($passage && trim((string) $passage->body) === '' && $passage->media->isEmpty()) {
            $blockers[] = __('The passage has no text and no image.');
        }

        return $blockers;
    }
}
