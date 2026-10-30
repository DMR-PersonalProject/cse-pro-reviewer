<?php

namespace App\Policies;

use App\Models\Source;
use App\Models\User;

/**
 * Sources are admin-only. Deleting goes through DeleteSource with typed confirmation (M2-11).
 */
class SourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Source $source): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Source $source): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Source $source): bool
    {
        return $user->isAdmin();
    }
}
