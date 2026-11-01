<?php

use App\Domain\Leaderboard\LeaderboardQuery;
use App\Domain\Leaderboard\Period;
use App\Domain\Leaderboard\SetAttemptHidden;
use App\Enums\AttemptMode;
use App\Filament\Resources\Attempts\AttemptResource;
use App\Filament\Resources\Attempts\Pages\ListAttempts;
use App\Models\Attempt;
use App\Models\AttemptResult;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function moderatedMock(array $attributes = []): Attempt
{
    $attempt = Attempt::factory()->submitted()->create([
        'user_id' => User::factory()->withPin()->create(['ign' => 'Ana'])->id,
        'mode' => AttemptMode::Combined,
        'subtest_id' => null,
        'item_count' => 170,
        'is_standard' => true,
        ...$attributes,
    ]);
    AttemptResult::factory()->create(['attempt_id' => $attempt->id, 'general_rating' => '90.00']);

    return $attempt;
}

function boardIgns(): array
{
    return collect(app(LeaderboardQuery::class)->mock(Period::AllTime)->items())->pluck('ign')->all();
}

it('AC-16b: a suspicious attempt is badged, filterable and still listed until hidden', function () {
    $suspicious = moderatedMock(['is_suspicious' => true, 'time_used_seconds' => 510]);
    $normal = Attempt::factory()->submitted()->create();

    livewire(ListAttempts::class)
        ->assertCanSeeTableRecords([$suspicious, $normal])
        ->assertSee(__('Suspicious'))
        ->filterTable('is_suspicious', true)
        ->assertCanSeeTableRecords([$suspicious])
        ->assertCanNotSeeTableRecords([$normal]);

    expect(boardIgns())->toBe(['Ana']);

    livewire(ListAttempts::class)
        ->callAction(TestAction::make('hide')->table($suspicious), data: ['reason' => 'Answered in 3 seconds each'])
        ->assertHasNoActionErrors();

    expect(boardIgns())->toBe([]);
});

it('requires a reason to hide', function () {
    $attempt = moderatedMock();

    livewire(ListAttempts::class)
        ->callAction(TestAction::make('hide')->table($attempt), data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($attempt->fresh()->hidden_at)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
    expect(fn () => app(SetAttemptHidden::class)->handle($attempt, true, '   '))->toThrow(ValidationException::class);
});

it('hide then unhide restores the entry, and both are audited', function () {
    $attempt = moderatedMock();

    livewire(ListAttempts::class)
        ->callAction(TestAction::make('hide')->table($attempt), data: ['reason' => 'Shared account'])
        ->assertHasNoActionErrors();

    $hidden = $attempt->fresh();
    expect($hidden->hidden_at)->not->toBeNull()
        ->and($hidden->hidden_by)->toBe($this->admin->id)
        ->and($hidden->hidden_reason)->toBe('Shared account')
        ->and(boardIgns())->toBe([]);

    livewire(ListAttempts::class)
        ->assertActionHidden(TestAction::make('hide')->table($hidden))
        ->callAction(TestAction::make('unhide')->table($hidden), data: ['reason' => 'Owner explained'])
        ->assertHasNoActionErrors();

    $restored = $attempt->fresh();
    expect($restored->hidden_at)->toBeNull()
        ->and($restored->hidden_by)->toBeNull()
        ->and($restored->hidden_reason)->toBeNull()
        ->and(boardIgns())->toBe(['Ana']);

    $logs = AuditLog::query()->orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['attempt.hidden', 'attempt.unhidden'])
        ->and($logs->pluck('reason')->all())->toBe(['Shared account', 'Owner explained'])
        ->and($logs->pluck('user_id')->unique()->all())->toBe([$this->admin->id])
        ->and($logs->pluck('subject_id')->unique()->all())->toBe([$attempt->id]);
});

it('requires a reason to unhide', function () {
    $attempt = moderatedMock(['hidden_at' => now(), 'hidden_reason' => 'x']);

    livewire(ListAttempts::class)
        ->callAction(TestAction::make('unhide')->table($attempt), data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($attempt->fresh()->hidden_at)->not->toBeNull();
});

it('shows read-only details', function () {
    $attempt = moderatedMock();

    livewire(ListAttempts::class)
        ->mountAction(TestAction::make('view')->table($attempt))
        ->assertSee('90.00');

    expect(AttemptResource::canCreate())->toBeFalse()
        ->and(AttemptResource::canEdit($attempt))->toBeFalse()
        ->and(AttemptResource::canDelete($attempt))->toBeFalse();
});

it('gives users a 403', function () {
    $this->actingAs(User::factory()->create());

    $this->get(AttemptResource::getUrl('index'))->assertForbidden();
});

it('serves the list to admins', function () {
    moderatedMock();

    $this->get(AttemptResource::getUrl('index'))->assertOk();
});
