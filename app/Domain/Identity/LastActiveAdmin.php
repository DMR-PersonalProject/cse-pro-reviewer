<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Enums\Role;
use App\Models\User;
use DomainException;

/**
 * The panel must always keep one active admin: they can't be demoted, deactivated or deleted (M2-13).
 */
class LastActiveAdmin
{
    /**
     * @throws DomainException
     */
    public static function guard(User $user): void
    {
        if (! $user->isAdmin() || ! $user->is_active) {
            return;
        }

        $others = User::query()
            ->where('role', Role::Admin)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();

        if (! $others) {
            throw new DomainException(__('This is the last active admin. Add or activate another admin first.'));
        }
    }
}
