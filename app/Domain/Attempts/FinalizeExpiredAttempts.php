<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Enums\AttemptStatus;
use App\Enums\AttemptTiming;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finalizes timed attempts whose deadline plus grace has passed, as timeout (plan §5 rules 5 and 6).
 */
final class FinalizeExpiredAttempts
{
    public function __construct(private readonly AttemptClock $clock) {}

    public function forUser(User $user): int
    {
        return $this->finalize($this->candidates()->where('user_id', $user->id));
    }

    public function all(): int
    {
        return $this->finalize($this->candidates());
    }

    /**
     * Past the deadline; the grace lives in each attempt's config, so the clock decides.
     *
     * @return Builder<Attempt>
     */
    private function candidates(): Builder
    {
        return Attempt::query()
            ->where('status', AttemptStatus::InProgress)
            ->where('timing', AttemptTiming::Timed)
            ->where('deadline_at', '<', now());
    }

    /**
     * @param  Builder<Attempt>  $query
     */
    private function finalize(Builder $query): int
    {
        $count = 0;

        $query->each(function (Attempt $attempt) use (&$count) {
            $count += $this->clock->guard($attempt) ? 1 : 0;
        });

        return $count;
    }
}
