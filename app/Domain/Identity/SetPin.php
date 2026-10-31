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
 * Changing an existing PIN needs the current one, rate-limited (#17, #19); setting a first PIN doesn't.
 */
class SetPin
{
    public function __construct(private readonly LoginThrottle $throttle) {}

    /**
     * @throws AuthorizationException for admins
     * @throws ValidationException for a wrong current PIN or a lockout (key `current_pin`), or an invalid new one
     */
    public function handle(User $user, string $pin, string $confirmation, ?string $currentPin = null): void
    {
        if (! $user->hasRole(Role::User)) {
            throw new AuthorizationException(__('Only user accounts have a PIN.'));
        }

        if ($user->hasPin()) {
            $this->throttle->checkCurrentPin($user, (string) $currentPin);
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
