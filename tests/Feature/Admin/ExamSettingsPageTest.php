<?php

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Filament\Pages\ExamSettings;
use App\Models\AuditLog;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->settings = app(Settings::class);
});

it('is admin only', function () {
    $this->get(ExamSettings::getUrl())->assertOk();

    $this->actingAs(User::factory()->create());
    $this->get(ExamSettings::getUrl())->assertForbidden();
});

it('shows every setting filled with its current value and its default', function () {
    $this->settings->set(SettingKey::TotalMinutes, 120);

    $page = livewire(ExamSettings::class)->assertFormSet([SettingKey::TotalMinutes->value => 120]);

    foreach (SettingKey::cases() as $key) {
        $page->assertFormFieldExists($key->value);
    }

    $page->assertSee(__('Default: :value', ['value' => '190']))
        ->assertSee(__('Default: :value', ['value' => '80.00']));
});

it('labels the item split as unverified', function () {
    livewire(ExamSettings::class)
        ->assertSee(__('Unverified'))
        ->assertSee(__('CSC doesn’t publish the item count per subtest.'));
});

it('shows a live total of the item split', function () {
    livewire(ExamSettings::class)
        ->assertSee(__('Total: :total items', ['total' => 170]))
        ->fillForm([SettingKey::ItemSplitVerbal->value => 60])
        ->assertSee(__('Total: :total items', ['total' => 180]));
});

it('rejects invalid values and saves nothing', function (SettingKey $key, mixed $value) {
    livewire(ExamSettings::class)
        ->fillForm([$key->value => $value])
        ->call('save')
        ->assertHasFormErrors([$key->value]);

    expect($this->settings->get($key))->toBe($key->default())
        ->and(AuditLog::where('action', 'settings.updated')->exists())->toBeFalse();
})->with([
    'split below 1' => [SettingKey::ItemSplitVerbal, 0],
    'split above 100' => [SettingKey::ItemSplitGeneralInfo, 101],
    'minutes below 10' => [SettingKey::TotalMinutes, 5],
    'passing above 100' => [SettingKey::PassingGrade, 100.5],
    'passing with 3 decimals' => [SettingKey::PassingGrade, 80.125],
    'threshold below 0.5' => [SettingKey::DuplicateSimilarityThreshold, 0.4],
    'lockout attempts 0' => [SettingKey::LockoutMaxAttempts, 0],
    'empty minutes' => [SettingKey::TotalMinutes, null],
]);

it('saves changes, clears the cache and writes one audit entry with old and new values', function () {
    expect($this->settings->get(SettingKey::TotalMinutes))->toBe(190); // primes the cache

    livewire(ExamSettings::class)
        ->fillForm([
            SettingKey::TotalMinutes->value => 200,
            SettingKey::PassingGrade->value => 75.5,
            SettingKey::LeaderboardIncludePrivate->value => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('Settings saved'));

    expect($this->settings->get(SettingKey::TotalMinutes))->toBe(200)
        ->and($this->settings->get(SettingKey::PassingGrade))->toBe(75.5)
        ->and($this->settings->get(SettingKey::LeaderboardIncludePrivate))->toBeTrue();

    $log = AuditLog::where('action', 'settings.updated')->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->meta['changes'])->toEqual([
            'total_minutes' => ['old' => 190, 'new' => 200],
            'passing_grade' => ['old' => 80.0, 'new' => 75.5],
            'leaderboard_include_private' => ['old' => false, 'new' => true],
        ]);
});

it('writes no audit entry when nothing changed', function () {
    livewire(ExamSettings::class)
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('Nothing changed'));

    expect(AuditLog::where('action', 'settings.updated')->exists())->toBeFalse();
});
