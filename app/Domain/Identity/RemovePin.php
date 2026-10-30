<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Removes a user's PIN after checking the current one (FR-I2).
 */
class RemovePin
{
    /**
     * @throws ValidationException when the current PIN is wrong
     */
    public function handle(User $user, string $currentPin): void
    {
        if (! $user->hasPin() || $currentPin === '' || ! Hash::check($currentPin, $user->password)) {
            throw ValidationException::withMessages([
                'current_pin' => __('That isn’t your current PIN.'),
            ]);
        }

        $user->forceFill(['password' => null, 'pin_set_at' => null])->save();
    }
}
