<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function makeAdmin(array $answers = [])
{
    $answers = array_merge([
        'email' => 'admin@example.com',
        'username' => 'admin',
        'ign' => 'Admin',
        'full_name' => 'Ada Admin',
        'password' => 'correct-horse',
    ], $answers);

    return test()->artisan('cse:make-admin')
        ->expectsQuestion('Email', $answers['email'])
        ->expectsQuestion('Username', $answers['username'])
        ->expectsQuestion('IGN', $answers['ign'])
        ->expectsQuestion('Full name', $answers['full_name'])
        ->expectsQuestion('Password', $answers['password'])
        ->expectsQuestion('Confirm password', $answers['password']);
}

it('creates an active admin with a hashed password', function () {
    makeAdmin()->assertSuccessful();

    $admin = User::sole();
    expect($admin)
        ->role->toBe(Role::Admin)
        ->is_active->toBeTrue()
        ->email->toBe('admin@example.com')
        ->username->toBe('admin')
        ->and($admin->password)->not->toBe('correct-horse')
        ->and(Hash::check('correct-horse', $admin->password))->toBeTrue()
        ->and(AuditLog::where('action', 'user.admin_created')->where('subject_id', $admin->id)->exists())->toBeTrue();
});

it('refuses a duplicate email', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    makeAdmin(['username' => 'admin2', 'ign' => 'Admin Two'])->assertFailed();

    expect(User::count())->toBe(1);
});

it('refuses invalid answers', function (array $answers) {
    User::factory()->create(['username' => 'taken', 'ign' => 'Taken Ign']);

    makeAdmin($answers)->assertFailed();

    expect(User::count())->toBe(1);
})->with([
    'bad email' => [['email' => 'not-an-email']],
    'username taken' => [['username' => 'TAKEN']],
    'IGN taken' => [['ign' => 'taken ign']],
    'bad username' => [['username' => 'a!']],
    'short password' => [['password' => 'short']],
]);

it('has no default credentials in the seeders (A-18)', function () {
    $this->seed();

    expect(User::count())->toBe(0);
});
