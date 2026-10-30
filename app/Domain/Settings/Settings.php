<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Typed, cached access to the settings table. All writes go through set() so they are validated.
 */
class Settings
{
    public const CACHE_KEY = 'cse.settings';

    public function get(SettingKey $key): int|float|bool
    {
        $stored = $this->all();

        return array_key_exists($key->value, $stored)
            ? $key->type()->cast($stored[$key->value])
            : $key->default();
    }

    /**
     * @throws ValidationException when the value is outside the setting's rules
     */
    public function set(SettingKey $key, mixed $value, ?User $by = null): void
    {
        Validator::make(
            ['value' => $value],
            ['value' => ['required', ...$key->rules()]],
            attributes: ['value' => str_replace('_', ' ', $key->value)],
        )->validate();

        Setting::updateOrCreate(
            ['key' => $key->value],
            ['value' => $key->type()->cast($value), 'updated_by' => $by?->id],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Items per subtest in the fixed order, keyed by subtest code.
     *
     * @return array<string, int>
     */
    public function itemSplit(): array
    {
        return array_map(fn (SettingKey $key) => (int) $this->get($key), SettingKey::itemSplit());
    }

    public function itemTotal(): int
    {
        return array_sum($this->itemSplit());
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
