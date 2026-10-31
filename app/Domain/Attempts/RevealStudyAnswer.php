<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Domain\Attempts\Exceptions\AnswerRefused;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\User;

/**
 * Study mode (FR-T8): choosing selects, locks and reveals the item in one step.
 */
final class RevealStudyAnswer
{
    public function __construct(private readonly AttemptItems $items) {}

    public function handle(User $user, Attempt $attempt, int $position, string $key): AttemptAnswer
    {
        return $this->items->change($user, $attempt, $position, function (AttemptAnswer $answer, Attempt $locked) use ($key) {
            if ($locked->timing !== AttemptTiming::Study) {
                throw new AnswerRefused(__('Answers are shown only in study mode.'));
            }

            if ($answer->revealed_at !== null) {
                throw new AnswerRefused(__('This answer is already final.'));
            }

            $this->items->assertChoice($answer, $key);

            $answer->update([
                'selected_key' => $key,
                'is_correct' => $key === $answer->correct_key,
                'answered_at' => now(),
                'revealed_at' => now(),
            ]);

            return $answer;
        });
    }
}
