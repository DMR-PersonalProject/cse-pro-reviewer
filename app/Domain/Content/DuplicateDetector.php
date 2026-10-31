<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\Question;

/**
 * Finds the closest existing item in the same subtest (FR-Q6). A warning only; it never blocks.
 * Compared in PHP (no engine-specific SQL), with a length prefilter before similar_text.
 */
class DuplicateDetector
{
    public function __construct(
        private readonly StemNormalizer $normalizer,
        private readonly Settings $settings,
    ) {}

    public function find(string $stem, int $subtestId, ?int $ignoreQuestionId = null): ?DuplicateMatch
    {
        $needle = $this->normalizer->normalize($stem);

        if ($needle === '') {
            return null;
        }

        $threshold = $this->threshold();
        $bestId = null;
        $bestSimilarity = 0.0;

        $candidates = Question::query()
            ->where('subtest_id', $subtestId)
            ->when($ignoreQuestionId, fn ($query) => $query->whereKeyNot($ignoreQuestionId))
            ->whereNotNull('stem_normalized')
            ->select(['id', 'stem_normalized'])
            ->lazyById(500);

        foreach ($candidates as $candidate) {
            $similarity = $this->similarity($needle, $candidate->stem_normalized, $threshold);

            if ($similarity !== null && $similarity > $bestSimilarity) {
                $bestId = $candidate->id;
                $bestSimilarity = $similarity;
            }
        }

        return $bestId === null ? null : new DuplicateMatch(Question::findOrFail($bestId), round($bestSimilarity, 4));
    }

    public function threshold(): float
    {
        return (float) $this->settings->get(SettingKey::DuplicateSimilarityThreshold);
    }

    /**
     * Similarity (0..1) of two normalized stems when it reaches the threshold, otherwise null.
     */
    public function similarity(string $normalizedA, string $normalizedB, float $threshold): ?float
    {
        $lengthA = mb_strlen($normalizedA);
        $lengthB = mb_strlen($normalizedB);

        if ($lengthA === 0 || $lengthB === 0 || min($lengthA, $lengthB) / max($lengthA, $lengthB) < $this->minLengthRatio($threshold)) {
            return null;
        }

        similar_text($normalizedA, $normalizedB, $percent);
        $similarity = $percent / 100;

        return $similarity >= $threshold ? $similarity : null;
    }

    /**
     * Shortest/longest length ratio worth comparing: the spec's ±30%, loosened at low thresholds
     * so no possible match is skipped (similar_text can't exceed 2·short / (short + long)).
     */
    private function minLengthRatio(float $threshold): float
    {
        return min(0.7, $threshold / (2 - $threshold));
    }
}
