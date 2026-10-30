<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Audit\Audit;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * An admin clears a user's PIN (FR-I6). The account then signs in with the username alone and
 * leaves the leaderboard until its owner sets a new PIN. Admin passwords are not PINs.
 */
class ResetPin
{
    public function __construct(private readonly EndSessions $endSessions) {}

    /**
     * @throws DomainException for admin accounts or accounts without a PIN
     */
    public function handle(User $user): void
    {
        if (! $user->hasPin()) {
            throw new DomainException(__('Only user accounts with a PIN can have it reset.'));
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['password' => null, 'pin_set_at' => null])->save();
            $this->endSessions->handle($user);

            Audit::record('user.pin_reset', $user);
        });
    }
}
