<?php

use App\Domain\Identity\ChangeRole;
use App\Domain\Identity\ResetPin;
use App\Domain\Identity\SetPin;
use App\Domain\Identity\SetUserActive;
use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

use function Pest\Livewire\livewire;

function usersTable()
{
    return livewire(ListUsers::class);
}

function fakeSession(User $user): void
{
    DB::table('sessions')->insert([
        'id' => 'sess_'.$user->id.'_'.uniqid(),
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => time(),
    ]);
}

function sessionsOf(User $user): int
{
    return DB::table('sessions')->where('user_id', $user->id)->count();
}

/**
 * The audit row must never carry a PIN, a password or its hash.
 */
function expectNoSecrets(AuditLog $log, ?string $hash = null): void
{
    $json = json_encode($log->meta).' '.$log->reason;

    expect($json)->not->toContain('password')
        ->not->toContain('1234')
        ->not->toContain('S3cret-Admin-Pass');

    if ($hash !== null) {
        expect($json)->not->toContain($hash);
    }
}

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists users with username, IGN, full name, role, active and PIN columns', function () {
    $user = User::factory()->withPin()->create(['full_name' => 'Maria Clara']);

    usersTable()
        ->assertCanSeeTableRecords([$user, $this->admin])
        ->assertTableColumnExists('username')
        ->assertTableColumnExists('ign')
        ->assertTableColumnExists('full_name')
        ->assertTableColumnExists('role')
        ->assertTableColumnExists('is_active')
        ->assertTableColumnExists('has_pin')
        ->assertSee('Maria Clara');
});

it('gives non-admins a 403', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/users')->assertForbidden();
});

it('has no create or edit pages', function () {
    expect(Route::has('filament.admin.resources.users.create'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.users.edit'))->toBeFalse();
});

it('changes an IGN and audits it', function () {
    $user = User::factory()->create(['ign' => 'OldName']);

    usersTable()
        ->callAction(TestAction::make('changeIgn')->table($user), data: ['ign' => 'New Name'])
        ->assertHasNoActionErrors();

    expect($user->fresh()->ign)->toBe('New Name');

    $log = AuditLog::where('action', 'user.ign_changed')->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($user->id)
        ->and($log->meta)->toMatchArray(['old' => 'OldName', 'new' => 'New Name']);
});

it('refuses an invalid or taken IGN', function (string $ign) {
    User::factory()->create(['ign' => 'Taken Name']);
    $user = User::factory()->create(['ign' => 'OldName']);

    usersTable()
        ->callAction(TestAction::make('changeIgn')->table($user), data: ['ign' => $ign])
        ->assertHasActionErrors(['ign']);

    expect($user->fresh()->ign)->toBe('OldName')
        ->and(AuditLog::where('action', 'user.ign_changed')->exists())->toBeFalse();
})->with(['too short' => 'ab', 'bad characters' => 'bad!name', 'taken, other case' => 'taken name']);

it('deactivates and activates a user, audits both and ends their sessions', function () {
    $user = User::factory()->create();
    fakeSession($user);

    usersTable()->callAction(TestAction::make('deactivate')->table($user));
    expect($user->fresh()->is_active)->toBeFalse()
        ->and(sessionsOf($user))->toBe(0);

    usersTable()->callAction(TestAction::make('activate')->table($user->fresh()));
    expect($user->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::where('action', 'user.deactivated')->where('subject_id', $user->id)->count())->toBe(1)
        ->and(AuditLog::where('action', 'user.activated')->where('subject_id', $user->id)->count())->toBe(1);
});

it('shows activate only for inactive users and deactivate only for active ones', function () {
    $active = User::factory()->create();
    $inactive = User::factory()->inactive()->create();

    usersTable()
        ->assertActionVisible(TestAction::make('deactivate')->table($active))
        ->assertActionHidden(TestAction::make('activate')->table($active))
        ->assertActionVisible(TestAction::make('activate')->table($inactive))
        ->assertActionHidden(TestAction::make('deactivate')->table($inactive));
});

it('refuses to deactivate the last active admin', function () {
    usersTable()
        ->callAction(TestAction::make('deactivate')->table($this->admin))
        ->assertNotified(__('Not possible'));

    expect($this->admin->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::where('action', 'user.deactivated')->exists())->toBeFalse();
});

it('lets an admin be deactivated while another active admin remains', function () {
    $other = User::factory()->admin()->create();
    User::factory()->admin()->inactive()->create();

    app(SetUserActive::class)->handle($other, false);

    expect($other->fresh()->is_active)->toBeFalse();
    expect(fn () => app(SetUserActive::class)->handle($this->admin, false))->toThrow(DomainException::class);
});

it('promotes a user to admin with an email and password, ending their sessions', function () {
    $user = User::factory()->withPin('1234')->create();
    fakeSession($user);

    usersTable()
        ->callAction(TestAction::make('changeRole')->table($user), data: [
            'role' => Role::Admin->value,
            'email' => 'new.admin@example.com',
            'password' => 'S3cret-Admin-Pass',
            'password_confirmation' => 'S3cret-Admin-Pass',
        ])
        ->assertHasNoActionErrors();

    $user->refresh();
    expect($user->role)->toBe(Role::Admin)
        ->and($user->email)->toBe('new.admin@example.com')
        ->and(Hash::check('S3cret-Admin-Pass', $user->password))->toBeTrue()
        ->and(Hash::check('1234', $user->password))->toBeFalse()
        ->and($user->pin_set_at)->toBeNull()
        ->and(sessionsOf($user))->toBe(0);

    $log = AuditLog::where('action', 'user.role_changed')->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($user->id)
        ->and($log->meta)->toMatchArray(['old' => 'user', 'new' => 'admin']);
    expectNoSecrets($log, $user->password);
});

it('needs a valid email and a confirmed strong password to promote', function (array $data, string $field) {
    User::factory()->admin()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    usersTable()
        ->callAction(TestAction::make('changeRole')->table($user), data: [
            'role' => Role::Admin->value,
            'email' => 'ok@example.com',
            'password' => 'S3cret-Admin-Pass',
            'password_confirmation' => 'S3cret-Admin-Pass',
            ...$data,
        ])
        ->assertHasActionErrors([$field]);

    expect($user->fresh()->role)->toBe(Role::User);
})->with([
    'no email' => [['email' => ''], 'email'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'taken email' => [['email' => 'taken@example.com'], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'Different-Pass-1'], 'password'],
]);

it('demotes an admin to user, clearing the password and ending their sessions', function () {
    $other = User::factory()->admin()->create();
    fakeSession($other);

    usersTable()
        ->callAction(TestAction::make('changeRole')->table($other), data: ['role' => Role::User->value])
        ->assertHasNoActionErrors();

    $other->refresh();
    expect($other->role)->toBe(Role::User)
        ->and($other->password)->toBeNull()
        ->and($other->hasPin())->toBeFalse()
        ->and($other->email)->not->toBeNull()
        ->and(sessionsOf($other))->toBe(0);

    $log = AuditLog::where('action', 'user.role_changed')->sole();
    expect($log->meta)->toMatchArray(['old' => 'admin', 'new' => 'user']);
    expectNoSecrets($log);
});

it('does not let an admin change their own role', function () {
    User::factory()->admin()->create();

    usersTable()->assertActionHidden(TestAction::make('changeRole')->table($this->admin));

    expect(fn () => app(ChangeRole::class)->handle($this->admin, Role::User))->toThrow(DomainException::class)
        ->and($this->admin->fresh()->role)->toBe(Role::Admin);
});

it('refuses to demote the last active admin', function () {
    $lastActive = User::factory()->admin()->create();
    $this->admin->forceFill(['is_active' => false])->save();
    $this->actingAs(User::factory()->admin()->inactive()->create());

    expect(fn () => app(ChangeRole::class)->handle($lastActive, Role::User))
        ->toThrow(DomainException::class, __('This is the last active admin. Add or activate another admin first.'));

    expect($lastActive->fresh()->role)->toBe(Role::Admin)
        ->and(AuditLog::where('action', 'user.role_changed')->exists())->toBeFalse();
});

it('resets a user\'s PIN after confirmation, audits it and ends their sessions', function () {
    $user = User::factory()->withPin('1234')->create();
    $hash = $user->password;
    fakeSession($user);

    usersTable()
        ->assertActionExists(TestAction::make('resetPin')->table($user), fn ($action) => $action->isConfirmationRequired())
        ->callAction(TestAction::make('resetPin')->table($user));

    $user->refresh();
    expect($user->password)->toBeNull()
        ->and($user->pin_set_at)->toBeNull()
        ->and($user->hasPin())->toBeFalse()
        ->and(sessionsOf($user))->toBe(0);

    $log = AuditLog::where('action', 'user.pin_reset')->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($user->id)
        ->and($log->created_at)->not->toBeNull();
    expectNoSecrets($log, $hash);
});

it('offers reset PIN only for user accounts that have a PIN', function () {
    $other = User::factory()->admin()->create();
    $noPin = User::factory()->create();

    usersTable()
        ->assertActionHidden(TestAction::make('resetPin')->table($other))
        ->assertActionHidden(TestAction::make('resetPin')->table($noPin));

    expect(fn () => app(ResetPin::class)->handle($other))->toThrow(DomainException::class)
        ->and($other->fresh()->password)->not->toBeNull();
});

it('drops a user from the leaderboard until they set a new PIN', function () {
    $user = User::factory()->withPin()->create();
    expect(User::leaderboardEligible()->whereKey($user->id)->exists())->toBeTrue();

    app(ResetPin::class)->handle($user);
    expect(User::leaderboardEligible()->whereKey($user->id)->exists())->toBeFalse();

    app(SetPin::class)->handle($user->fresh(), '5678', '5678');
    expect(User::leaderboardEligible()->whereKey($user->id)->exists())->toBeTrue();
});

it('keeps inactive users off the leaderboard', function () {
    $user = User::factory()->withPin()->inactive()->create();

    expect(User::leaderboardEligible()->whereKey($user->id)->exists())->toBeFalse();
});

it('deletes a user only when the username is typed, and audits it', function () {
    $user = User::factory()->create(['username' => 'juan_dc']);
    fakeSession($user);

    usersTable()
        ->callAction(TestAction::make('delete')->table($user), data: ['confirmation' => 'juan'])
        ->assertHasActionErrors(['confirmation']);
    expect(User::whereKey($user->id)->exists())->toBeTrue();

    usersTable()
        ->callAction(TestAction::make('delete')->table($user), data: ['confirmation' => 'juan_dc'])
        ->assertHasNoActionErrors();

    expect(User::whereKey($user->id)->exists())->toBeFalse()
        ->and(sessionsOf($user))->toBe(0);

    $log = AuditLog::where('action', 'user.deleted')->sole();
    expect($log->subject_id)->toBe($user->id)
        ->and($log->meta)->toMatchArray(['username' => 'juan_dc', 'role' => 'user']);
});

it('does not offer delete on the admin\'s own row', function () {
    usersTable()->assertActionHidden(TestAction::make('delete')->table($this->admin));
});
