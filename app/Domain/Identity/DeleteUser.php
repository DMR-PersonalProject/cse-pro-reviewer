<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Audit\Audit;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * An admin deletes an account after typing its username (FR-I6). The user's attempts go with it
 * once attempts exist (M4 adds the cascading foreign keys).
 */
class DeleteUser
{
    public function __construct(private readonly EndSessions $endSessions) {}

    /**
     * @throws DomainException
     */
    public function handle(User $user, string $confirmation): void
    {
        if ($confirmation !== $user->username) {
            throw new DomainException(__('Type the username exactly to confirm.'));
        }

        if (Auth::user()?->is($user)) {
            throw new DomainException(__('You can’t delete your own account.'));
        }

        LastActiveAdmin::guard($user);

        DB::transaction(function () use ($user) {
            $this->endSessions->handle($user);
            $user->delete();

            Audit::record('user.deleted', $user, meta: [
                'username' => $user->username,
                'ign' => $user->ign,
                'role' => $user->role->value,
            ]);
        });
    }
}
