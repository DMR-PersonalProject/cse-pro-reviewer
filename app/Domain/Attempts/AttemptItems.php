<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Domain\Attempts\Exceptions\AnswerRefused;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Runs a change to one item with the attempt locked, so it can't race a finalize.
 * Every answer action goes through here: owner only, in progress, before deadline plus grace.
 */
final class AttemptItems
{
    public function __construct(private readonly AttemptClock $clock) {}

    /**
     * @template T
     *
     * @param  Closure(AttemptAnswer, Attempt): T  $change
     * @return T
     */
    public function change(User $user, Attempt $attempt, int $position, Closure $change): mixed
    {
        return DB::transaction(function () use ($user, $attempt, $position, $change) {
            $locked = Attempt::query()->lockForUpdate()->findOrFail($attempt->id);
            Gate::forUser($user)->authorize('update', $locked);

            if (! $this->clock->acceptsAnswers($locked)) {
                throw new AnswerRefused(__('Time is up. This answer was not saved.'));
            }

            $answer = $locked->answers()->where('position', $position)->first()
                ?? throw new AnswerRefused(__('This item is not part of the test.'));

            return $change($answer, $locked);
        });
    }

    /**
     * @throws AnswerRefused when the key is not one of the item's shown choices
     */
    public function assertChoice(AttemptAnswer $answer, string $key): void
    {
        if (! in_array($key, array_column($answer->snapshot['choices'], 'key'), true)) {
            throw new AnswerRefused(__('That choice is not available for this item.'));
        }
    }
}
