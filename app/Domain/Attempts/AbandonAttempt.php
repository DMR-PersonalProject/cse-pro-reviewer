<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\AttemptResult;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Ends an attempt the user gave up on (FR-G9). It keeps a result with the abandoned end
 * reason (FR-S4) and never ranks.
 */
final class AbandonAttempt
{
    public function __construct(private readonly FinalizeAttempt $finalize) {}

    public function handle(User $user, Attempt $attempt): AttemptResult
    {
        Gate::forUser($user)->authorize('update', $attempt->fresh());

        return $this->finalize->handle($attempt, AttemptStatus::Abandoned);
    }
}
