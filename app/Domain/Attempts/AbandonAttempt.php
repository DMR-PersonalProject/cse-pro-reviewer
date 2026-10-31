<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Ends an attempt the user gave up on (FR-G9). It stays in history and never ranks.
 */
final class AbandonAttempt
{
    public function handle(User $user, Attempt $attempt): Attempt
    {
        return DB::transaction(function () use ($user, $attempt) {
            $locked = Attempt::query()->lockForUpdate()->findOrFail($attempt->id);
            Gate::forUser($user)->authorize('update', $locked);

            $locked->update(['status' => AttemptStatus::Abandoned, 'finalized_at' => now()]);

            return $locked;
        });
    }
}
