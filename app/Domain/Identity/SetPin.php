<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Sets or changes a user's PIN (FR-I2). The PIN lives in the password column, so admins are refused.
 */
class SetPin
{
    /**
     * @throws AuthorizationException for admins
     * @throws ValidationException
     */
    public function handle(User $user, string $pin, string $confirmation): void
    {
        if (! $user->hasRole(Role::User)) {
            throw new AuthorizationException(__('Only user accounts have a PIN.'));
        }

        Validator::make(
            ['pin' => $pin, 'pin_confirmation' => $confirmation],
            ['pin' => ['required', 'digits_between:4,6', 'confirmed']],
            [
                'pin.digits_between' => __('The PIN must be 4–6 digits.'),
                'pin.confirmed' => __('The PINs don’t match.'),
            ],
            ['pin' => 'PIN'],
        )->validate();

        $user->forceFill(['password' => $pin, 'pin_set_at' => now()])->save();
    }
}
