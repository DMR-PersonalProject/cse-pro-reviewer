<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sign-in rate limit per username plus IP, with limits from settings (FR-I4).
 */
class LoginThrottle
{
    public function __construct(private readonly Settings $settings) {}

    public function key(string $username, string $ip): string
    {
        return 'login:'.mb_strtolower(trim($username)).'|'.$ip;
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
}
