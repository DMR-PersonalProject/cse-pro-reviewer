<?php

declare(strict_types=1);

namespace App\Domain\Scoring;

use App\Domain\Scoring\Formulas\GeneralRatingV1;
use App\Models\AttemptAnswer;
use Illuminate\Support\Collection;

/**
 * Scores an attempt's answer rows (FR-S1 to FR-S3). Unanswered counts as wrong; there is no
 * penalty for a wrong answer.
 */
final class Scorer
{
    public function __construct(private readonly GeneralRatingV1 $formula) {}

    /**
     * @param  Collection<int, AttemptAnswer>  $answers
     * @param  float  $passingGrade  from the attempt's config, e.g. 80.0
     */
    public function score(Collection $answers, float $passingGrade): ScoreBreakdown
    {
        $correct = $answers->filter(fn (AttemptAnswer $answer) => $this->isCorrect($answer))->count();
        $total = $answers->count();
        $rating = $this->formula->hundredths($correct, $total);
        $passingHundredths = (int) round($passingGrade * 100);

        return new ScoreBreakdown(
            formulaVersion: GeneralRatingV1::VERSION,
            correct: $correct,
            total: $total,
            generalRating: GeneralRatingV1::format($rating),
            passingGrade: GeneralRatingV1::format($passingHundredths),
            passed: $total > 0 && $rating >= $passingHundredths,
            subtests: $this->breakdown($answers, 'subtest_id'),
            topics: $this->breakdown($answers, 'topic_id'),
        );
    }

    public function isCorrect(AttemptAnswer $answer): bool
    {
        return $answer->selected_key !== null && $answer->selected_key === $answer->correct_key;
    }

    /**
     * @param  Collection<int, AttemptAnswer>  $answers
     * @return array<int, array{correct: int, total: int, percent: string}>
     */
    private function breakdown(Collection $answers, string $column): array
    {
        return $answers->groupBy($column)
            ->sortKeys()
            ->map(function (Collection $group) {
                $correct = $group->filter(fn (AttemptAnswer $answer) => $this->isCorrect($answer))->count();

                return [
                    'correct' => $correct,
                    'total' => $group->count(),
                    'percent' => $this->formula->percent($correct, $group->count()),
                ];
            })
            ->all();
    }
}
