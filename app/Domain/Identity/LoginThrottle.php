<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * PIN check rate limits with limits from settings (FR-I4): sign-in per username plus IP,
 * and the profile's current-PIN check per user (#19).
 */
class LoginThrottle
{
    public function __construct(private readonly Settings $settings) {}

    public function key(string $username, string $ip): string
    {
        return 'login:'.mb_strtolower(trim($username)).'|'.$ip;
    }

    public function currentPinKey(User $user): string
    {
        return 'pin-check:'.$user->id;
    }

    public function tooManyAttempts(string $key): bool
    {
        return RateLimiter::tooManyAttempts($key, (int) $this->settings->get(SettingKey::LockoutMaxAttempts));
    }

    public function hit(string $key): void
    {
        RateLimiter::hit($key, (int) $this->settings->get(SettingKey::LockoutWindowMinutes) * 60);
    }

    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    public function secondsUntilAvailable(string $key): int
    {
        return RateLimiter::availableIn($key);
    }

    /**
     * Checks a signed-in user's current PIN, counting failures toward a lockout shared by the
     * change and remove forms. A right PIN clears the count.
     *
     * @throws ValidationException keyed to `current_pin` when locked out or the PIN is wrong
     */
    public function checkCurrentPin(User $user, string $currentPin): void
    {
        $key = $this->currentPinKey($user);

        if ($this->tooManyAttempts($key)) {
            throw ValidationException::withMessages([
                'current_pin' => __('Too many wrong PINs. Try again in :minutes min.', [
                    'minutes' => (int) ceil($this->secondsUntilAvailable($key) / 60),
                ]),
            ]);
        }

        if (! $user->hasPin() || $currentPin === '' || ! Hash::check($currentPin, $user->password)) {
            $this->hit($key);

            throw ValidationException::withMessages([
                'current_pin' => __('That isn’t your current PIN.'),
            ]);
        }

        $this->clear($key);
    }
}
