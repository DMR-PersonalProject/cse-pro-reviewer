<?php

namespace Database\Seeders;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SettingsSeeder extends Seeder
{
    /**
     * Write a row with the default for every setting that has none. Existing values are kept.
     */
    public function run(): void
    {
        foreach (SettingKey::cases() as $key) {
            Setting::firstOrCreate(['key' => $key->value], ['value' => $key->default()]);
        }

        Cache::forget(Settings::CACHE_KEY);
    }
}
