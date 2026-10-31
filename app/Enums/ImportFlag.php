<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why an import row arrives as needs_review (plan §8.3). The code stays on the row so the preview
 * can be specific; the item gets the matching NeedsReviewReason.
 */
enum ImportFlag: string implements HasLabel
{
    case MissingAnswerKey = 'missing_answer_key';
    case MissingExplanation = 'missing_explanation';
    case LowConfidence = 'low_confidence';
    case MediaMissing = 'media_missing';
    case MissingAltText = 'missing_alt_text';
    case PossibleDuplicate = 'possible_duplicate';
    case ExtractorFlagged = 'extractor_flagged';

    public function reason(): NeedsReviewReason
    {
        return match ($this) {
            self::MissingAnswerKey => NeedsReviewReason::MissingAnswerKey,
            self::MissingExplanation => NeedsReviewReason::MissingExplanation,
            self::LowConfidence => NeedsReviewReason::LowConfidence,
            // The image isn't attached without alt text, so to the item it is missing (#21).
            self::MediaMissing, self::MissingAltText => NeedsReviewReason::MissingMedia,
            self::PossibleDuplicate => NeedsReviewReason::PossibleDuplicate,
            self::ExtractorFlagged => NeedsReviewReason::FlaggedInSource,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MissingAnswerKey => __('No answer key'),
            self::MissingExplanation => __('No explanation'),
            self::LowConfidence => __('Low extraction confidence'),
            self::MediaMissing => __('Media missing'),
            self::MissingAltText => __('Image has no alt text'),
            self::PossibleDuplicate => __('Possible duplicate'),
            self::ExtractorFlagged => __('Flagged in the source file'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
