<?php

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->settings = app(Settings::class);
});

it('returns defaults when no row exists', function () {
    expect(Setting::count())->toBe(0)
        ->and($this->settings->get(SettingKey::TotalMinutes))->toBe(190)
        ->and($this->settings->get(SettingKey::PassingGrade))->toBe(80.0)
        ->and($this->settings->get(SettingKey::DuplicateSimilarityThreshold))->toBe(0.9)
        ->and($this->settings->get(SettingKey::LockoutMaxAttempts))->toBe(5)
        ->and($this->settings->get(SettingKey::LockoutWindowMinutes))->toBe(15)
        ->and($this->settings->get(SettingKey::RepeatWindowAttempts))->toBe(3)
        ->and($this->settings->get(SettingKey::AutosaveGraceSeconds))->toBe(10)
        ->and($this->settings->get(SettingKey::SuspiciousSecondsPerItem))->toBe(5)
        ->and($this->settings->get(SettingKey::LeaderboardIncludePrivate))->toBeFalse();
});

it('returns the stored value with the right type', function (SettingKey $key, mixed $value, mixed $expected) {
    $this->settings->set($key, $value);

    expect($this->settings->get($key))->toBe($expected);
})->with([
    'int' => [SettingKey::LockoutMaxAttempts, 3, 3],
    'int from a form string' => [SettingKey::TotalMinutes, '120', 120],
    'decimal' => [SettingKey::PassingGrade, 75.5, 75.5],
    'decimal from a form string' => [SettingKey::DuplicateSimilarityThreshold, '0.85', 0.85],
    'bool' => [SettingKey::LeaderboardIncludePrivate, true, true],
    'zero turns a check off' => [SettingKey::SuspiciousSecondsPerItem, 0, 0],
]);

it('rejects out-of-range values', function (SettingKey $key, mixed $value) {
    $this->settings->set($key, $value);
})->with([
    'similarity 0.3' => [SettingKey::DuplicateSimilarityThreshold, 0.3],
    'similarity above 1' => [SettingKey::DuplicateSimilarityThreshold, 1.01],
    '0 lockout attempts' => [SettingKey::LockoutMaxAttempts, 0],
    'lockout window above a day' => [SettingKey::LockoutWindowMinutes, 1441],
    'passing grade above 100' => [SettingKey::PassingGrade, 100.01],
    'passing grade with 3 decimals' => [SettingKey::PassingGrade, 80.005],
    'subtest with 0 items' => [SettingKey::ItemSplitVerbal, 0],
    'subtest with 101 items' => [SettingKey::ItemSplitGeneralInfo, 101],
    'total minutes below 10' => [SettingKey::TotalMinutes, 9],
    'negative repeat window' => [SettingKey::RepeatWindowAttempts, -1],
    'grace above 60' => [SettingKey::AutosaveGraceSeconds, 61],
    'suspicious above 60' => [SettingKey::SuspiciousSecondsPerItem, 61],
    'non-integer count' => [SettingKey::TotalMinutes, 12.5],
    'null' => [SettingKey::TotalMinutes, null],
    'non-boolean flag' => [SettingKey::LeaderboardIncludePrivate, 'maybe'],
])->throws(ValidationException::class);

it('keeps the old value when a write is rejected', function () {
    try {
        $this->settings->set(SettingKey::LockoutMaxAttempts, 0);
    } catch (ValidationException) {
    }

    expect($this->settings->get(SettingKey::LockoutMaxAttempts))->toBe(5)
        ->and(Setting::count())->toBe(0);
});

it('caches values and clears the cache on update', function () {
    $this->settings->set(SettingKey::LockoutMaxAttempts, 7);
    expect($this->settings->get(SettingKey::LockoutMaxAttempts))->toBe(7);

    // A write that bypasses the service is not seen: the value is cached.
    DB::table('settings')->where('key', 'lockout_max_attempts')->update(['value' => json_encode(9)]);
    expect($this->settings->get(SettingKey::LockoutMaxAttempts))->toBe(7);

    $this->settings->set(SettingKey::LockoutMaxAttempts, 3);
    expect($this->settings->get(SettingKey::LockoutMaxAttempts))->toBe(3);
});

it('records who changed a setting', function () {
    $admin = User::factory()->admin()->create();

    $this->settings->set(SettingKey::TotalMinutes, 180, $admin);

    expect(Setting::where('key', 'total_minutes')->value('updated_by'))->toBe($admin->id);
});

it('has an item split that defaults to 170 items in subtest order', function () {
    expect($this->settings->itemSplit())->toBe([
        'verbal' => 50,
        'numerical' => 40,
        'analytical' => 40,
        'general_info' => 40,
    ])->and($this->settings->itemTotal())->toBe(170);
});

it('labels only the item split as unverified', function () {
    $unverified = array_filter(SettingKey::cases(), fn (SettingKey $key) => $key->isUnverified());

    expect(array_values($unverified))->toBe([
        SettingKey::ItemSplitVerbal,
        SettingKey::ItemSplitNumerical,
        SettingKey::ItemSplitAnalytical,
        SettingKey::ItemSplitGeneralInfo,
    ]);
});

it('every default passes its own validation', function (SettingKey $key) {
    $this->settings->set($key, $key->default());

    expect($this->settings->get($key))->toBe($key->default());
})->with(SettingKey::cases());

it('seeds a row with the default for every setting, without overwriting edits', function () {
    $this->settings->set(SettingKey::TotalMinutes, 120);

    $this->seed(SettingsSeeder::class);

    expect(Setting::count())->toBe(count(SettingKey::cases()))
        ->and($this->settings->get(SettingKey::TotalMinutes))->toBe(120)
        ->and($this->settings->get(SettingKey::PassingGrade))->toBe(80.0);
});
