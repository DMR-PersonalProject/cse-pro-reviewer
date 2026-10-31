<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use Illuminate\Support\Carbon;

/**
 * Server time is the only clock (plan §5). The grace comes from the attempt's own config,
 * so changing the setting never affects a running attempt.
 */
final class AttemptClock
{
    public function __construct(private readonly FinalizeAttempt $finalize) {}

    /**
     * Lazy finalization: an expired attempt is finalized as timeout. True means the caller
     * must stop and show the result.
     */
    public function guard(Attempt $attempt): bool
    {
        if (! $this->isExpired($attempt)) {
            return false;
        }

        $this->finalize->handle($attempt, AttemptStatus::Timeout);

        return true;
    }

    /**
     * Milliseconds left, never negative; null when the attempt has no deadline.
     */
    public function remainingMs(Attempt $attempt): ?int
    {
        if ($attempt->deadline_at === null) {
            return null;
        }

        return max(0, (int) now()->diffInMilliseconds($attempt->deadline_at, false));
    }

    /**
     * Answers may be saved while in progress and up to the deadline plus grace.
     */
    public function acceptsAnswers(Attempt $attempt): bool
    {
        return $attempt->isInProgress()
            && ($attempt->deadline_at === null || now()->lessThanOrEqualTo($this->graceEnd($attempt)));
    }

    /**
     * A timed attempt still in progress after its deadline plus grace must be finalized as timeout.
     */
    public function isExpired(Attempt $attempt): bool
    {
        return $attempt->isInProgress()
            && $attempt->deadline_at !== null
            && now()->greaterThan($this->graceEnd($attempt));
    }

    /**
     * The client's time-up only counts once the deadline has really passed.
     */
    public function hasReachedDeadline(Attempt $attempt): bool
    {
        return $attempt->deadline_at !== null && now()->greaterThanOrEqualTo($attempt->deadline_at);
    }

    private function graceEnd(Attempt $attempt): Carbon
    {
        return $attempt->deadline_at->copy()->addSeconds((int) ($attempt->config['grace_seconds'] ?? 0));
    }
}
