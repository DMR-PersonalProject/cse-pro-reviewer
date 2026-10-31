<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Domain\Attempts\Exceptions\AnswerRefused;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\User;

/**
 * Stores or changes the choice for one item of a timed or untimed attempt.
 */
final class SaveAnswer
{
    public function __construct(private readonly AttemptItems $items) {}

    public function handle(User $user, Attempt $attempt, int $position, string $key): AttemptAnswer
    {
        return $this->items->change($user, $attempt, $position, function (AttemptAnswer $answer, Attempt $locked) use ($key) {
            // Study mode answers through RevealStudyAnswer, which also locks the item.
            if ($locked->timing === AttemptTiming::Study) {
                throw new AnswerRefused(__('In study mode, each answer is final once chosen.'));
            }

            $this->items->assertChoice($answer, $key);

            $answer->update([
                'selected_key' => $key,
                'is_correct' => $key === $answer->correct_key,
                'answered_at' => now(),
            ]);

            return $answer;
        });
    }
}
