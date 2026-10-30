<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * FR-Q2: draft → needs_review ↔ draft → published → archived, and archived → published.
 * Publishing also has content gates; see PublishQuestion.
 */
enum QuestionStatus: string implements HasLabel
{
    case Draft = 'draft';
    case NeedsReview = 'needs_review';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::NeedsReview, self::Published],
            self::NeedsReview => [self::Draft],
            self::Published => [self::Archived],
            self::Archived => [self::Published],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::NeedsReview => __('Needs review'),
            self::Published => __('Published'),
            self::Archived => __('Archived'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
