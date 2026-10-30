<?php

namespace App\Policies;

use App\Models\User;

/**
 * Base rules; M2-13 extends these for the Filament UserResource.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->isAdmin() || $actor->is($user);
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->isAdmin() && ! $actor->is($user);
    }
}
