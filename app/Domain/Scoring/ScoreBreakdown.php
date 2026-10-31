<?php

declare(strict_types=1);

namespace App\Domain\Scoring;

/**
 * A scored attempt. Percentages are decimal strings with 2 places, as stored.
 */
final readonly class ScoreBreakdown
{
    /**
     * @param  array<int, array{correct: int, total: int, percent: string}>  $subtests  by subtest id
     * @param  array<int, array{correct: int, total: int, percent: string}>  $topics  by topic id
     */
    public function __construct(
        public string $formulaVersion,
        public int $correct,
        public int $total,
        public string $generalRating,
        public string $passingGrade,
        public bool $passed,
        public array $subtests,
        public array $topics,
    ) {}
}
