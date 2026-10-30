<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Audit\Audit;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * An admin deactivates or reactivates an account (FR-I6). A deactivated account is signed out
 * everywhere and can't sign in; its data is kept.
 */
class SetUserActive
{
    public function __construct(private readonly EndSessions $endSessions) {}

    /**
     * @throws DomainException when deactivating the last active admin
     */
    public function handle(User $user, bool $active): void
    {
        if ($user->is_active === $active) {
            return;
        }

        if (! $active) {
            LastActiveAdmin::guard($user);
        }

        DB::transaction(function () use ($user, $active) {
            $user->forceFill(['is_active' => $active])->save();

            if (! $active) {
                $this->endSessions->handle($user);
            }

            Audit::record($active ? 'user.activated' : 'user.deactivated', $user);
        });
    }
}
