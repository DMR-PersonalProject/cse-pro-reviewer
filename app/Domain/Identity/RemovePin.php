<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Removes a user's PIN after checking the current one (FR-I2), rate-limited (#19).
 */
class RemovePin
{
    public function __construct(private readonly LoginThrottle $throttle) {}

    /**
     * @throws ValidationException when the current PIN is wrong or too many tries failed
     */
    public function handle(User $user, string $currentPin): void
    {
        $this->throttle->checkCurrentPin($user, $currentPin);

        $user->forceFill(['password' => null, 'pin_set_at' => null])->save();
    }
}
