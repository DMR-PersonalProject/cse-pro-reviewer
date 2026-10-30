<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why an item needs a human look before it can be published (FR-M4). An item can't be
 * published while any reason is unresolved (FR-Q2).
 */
enum NeedsReviewReason: string implements HasLabel
{
    case MissingAnswerKey = 'missing_answer_key';
    case MissingExplanation = 'missing_explanation';
    case PossibleDuplicate = 'possible_duplicate';
    case LowConfidence = 'low_confidence';
    case MissingMedia = 'missing_media';
    case FlaggedInSource = 'flagged_in_source';

    public function label(): string
    {
        return match ($this) {
            self::MissingAnswerKey => __('No answer key'),
            self::MissingExplanation => __('No explanation'),
            self::PossibleDuplicate => __('Possible duplicate'),
            self::LowConfidence => __('Low extraction confidence'),
            self::MissingMedia => __('Referenced media is missing'),
            self::FlaggedInSource => __('Flagged for review in the source file'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
