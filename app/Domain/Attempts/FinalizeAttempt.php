<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Domain\Scoring\Scorer;
use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\AttemptResult;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ends an attempt and writes its score snapshot (plan §5). The attempt row lock makes it
 * happen once: a second call returns the existing result (AC-8).
 */
final class FinalizeAttempt
{
    public function __construct(private readonly Scorer $scorer) {}

    public function handle(Attempt $attempt, AttemptStatus $reason): AttemptResult
    {
        if ($reason === AttemptStatus::InProgress) {
            throw new InvalidArgumentException('An attempt can\'t be finalized as in progress.');
        }

        return DB::transaction(function () use ($attempt, $reason) {
            $locked = Attempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (! $locked->isInProgress()) {
                return $locked->result()->firstOrFail();
            }

            $finalizedAt = now();
            $end = $locked->deadline_at !== null && $locked->deadline_at->lessThan($finalizedAt) ? $locked->deadline_at : $finalizedAt;
            $timeUsed = (int) $locked->started_at->diffInSeconds($end);
            $threshold = (int) ($locked->config['suspicious_seconds_per_item'] ?? 0);

            $score = $this->scorer->score($locked->answers()->get(), (float) $locked->config['passing_grade']);

            $locked->update([
                'status' => $reason,
                'finalized_at' => $finalizedAt,
                'time_used_seconds' => $timeUsed,
                'is_suspicious' => $threshold > 0 && $timeUsed < $threshold * $locked->item_count,
            ]);

            foreach ($score->subtests as $subtestId => $subtest) {
                $locked->subtestScores()->create(['subtest_id' => $subtestId, ...$subtest]);
            }

            return $locked->result()->create([
                'formula_version' => $score->formulaVersion,
                'general_rating' => $score->generalRating,
                'passing_grade' => $score->passingGrade,
                'passed' => $score->passed,
                'correct_count' => $score->correct,
                'total_count' => $score->total,
                'time_used_seconds' => $timeUsed,
                'end_reason' => $reason,
                'topic_breakdown' => $score->topics,
            ]);
        });
    }
}
