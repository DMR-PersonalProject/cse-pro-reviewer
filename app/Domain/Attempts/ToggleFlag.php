<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\User;

/**
 * Marks or unmarks an item for review.
 */
final class ToggleFlag
{
    public function __construct(private readonly AttemptItems $items) {}

    public function handle(User $user, Attempt $attempt, int $position): AttemptAnswer
    {
        return $this->items->change($user, $attempt, $position, function (AttemptAnswer $answer) {
            $answer->update(['flagged' => ! $answer->flagged]);

            return $answer;
        });
    }
}
