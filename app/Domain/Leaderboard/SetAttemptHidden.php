<?php

declare(strict_types=1);

namespace App\Domain\Leaderboard;

use App\Domain\Audit\Audit;
use App\Models\Attempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An admin hides or unhides an attempt from the leaderboard (FR-L5). Both need a reason and are audited.
 */
final class SetAttemptHidden
{
    /**
     * @throws ValidationException when the reason is blank
     */
    public function handle(Attempt $attempt, bool $hidden, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('A reason is required.')]);
        }

        if (($attempt->hidden_at !== null) === $hidden) {
            return;
        }

        DB::transaction(function () use ($attempt, $hidden, $reason) {
            $attempt->forceFill($hidden
                ? ['hidden_at' => now(), 'hidden_by' => Auth::id(), 'hidden_reason' => $reason]
                : ['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null])->save();

            Audit::record($hidden ? 'attempt.hidden' : 'attempt.unhidden', $attempt, $reason);
        });
    }
}
