<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Exceptions\QuestionNotPublishable;
use App\Enums\QuestionStatus;
use App\Models\Question;
use DomainException;

/**
 * FR-Q2 only allows archived → published, so unarchiving re-publishes through the same gates.
 */
class UnarchiveQuestion
{
    public function __construct(private readonly PublishQuestion $publish) {}

    /**
     * @throws DomainException when the item isn't archived
     * @throws QuestionNotPublishable
     */
    public function handle(Question $question): void
    {
        if ($question->status !== QuestionStatus::Archived) {
            throw new DomainException(__('Only archived items can be unarchived.'));
        }

        $this->publish->handle($question, auditAction: 'question.unarchived');
    }
}
