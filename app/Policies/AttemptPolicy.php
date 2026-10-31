<?php

namespace App\Policies;

use App\Models\Attempt;
use App\Models\User;

/**
 * Owners view and work on their own attempts; admins can view any. Nobody updates a finalized one.
 */
class AttemptPolicy
{
    public function view(User $user, Attempt $attempt): bool
    {
        return $user->isAdmin() || $attempt->user_id === $user->id;
    }

    public function update(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id && $attempt->isInProgress();
    }
}
