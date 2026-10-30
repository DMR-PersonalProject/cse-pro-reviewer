<?php

namespace App\Policies;

use App\Models\Passage;
use App\Models\User;

/**
 * Passages are admin-only.
 */
class PassagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Passage $passage): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Passage $passage): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Passage $passage): bool
    {
        return $user->isAdmin();
    }
}
