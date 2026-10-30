<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Checks a user's username and PIN (FR-I3, FR-I4). Admins sign in through Filament instead.
 */
class SignInWithUsername
{
    public function __construct(private readonly LoginThrottle $throttle) {}

    /**
     * @throws ValidationException on any refusal, keyed to "username"
     */
    public function handle(string $username, string $pin, string $ip): User
    {
        $key = $this->throttle->key($username, $ip);

        if ($this->throttle->tooManyAttempts($key)) {
            throw ValidationException::withMessages([
                'username' => __('Too many sign-in attempts. Try again in :minutes min.', [
                    'minutes' => (int) ceil($this->throttle->secondsUntilAvailable($key) / 60),
                ]),
            ]);
        }

        $user = User::where('username', mb_strtolower(trim($username)))->first();

        if (! $user || ! $user->hasRole(Role::User) || ! $this->pinMatches($user, $pin)) {
            $this->throttle->hit($key);

            throw ValidationException::withMessages([
                'username' => __('Username or PIN is incorrect.'),
            ]);
        }

        $this->throttle->clear($key);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => __('This account has been deactivated. Ask an admin for help.'),
            ]);
        }

        return $user;
    }

    private function pinMatches(User $user, string $pin): bool
    {
        return $user->hasPin()
            ? $pin !== '' && Hash::check($pin, $user->password)
            : true;
    }
}
