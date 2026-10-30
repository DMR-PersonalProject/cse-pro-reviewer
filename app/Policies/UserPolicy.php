<?php

namespace App\Policies;

use App\Models\User;

/**
 * Who may manage accounts. The finer guards (own role, last active admin) live in the
 * Domain\Identity actions, so they hold outside Filament too.
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
