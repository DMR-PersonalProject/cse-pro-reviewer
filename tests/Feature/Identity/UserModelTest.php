<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;

it('stores the username in lowercase', function () {
    $user = User::factory()->create(['username' => 'Juan']);

    expect($user->fresh()->username)->toBe('juan');
});

it('rejects a username that differs only in case (AC-2 at DB level)', function () {
    User::factory()->create(['username' => 'juan']);

    User::factory()->create(['username' => 'JUAN']);
})->throws(UniqueConstraintViolationException::class);

it('keeps the IGN as typed and normalizes a lowercase copy', function () {
    $user = User::factory()->create(['ign' => 'JuanPogi']);

    expect($user->fresh())
        ->ign->toBe('JuanPogi')
        ->ign_normalized->toBe('juanpogi');
});

it('rejects an IGN that differs only in case', function () {
    User::factory()->create(['ign' => 'JuanPogi']);

    User::factory()->create(['ign' => 'JUANPOGI']);
})->throws(UniqueConstraintViolationException::class);

it('allows several users without an email', function () {
    User::factory()->count(2)->create(['email' => null]);

    expect(User::whereNull('email')->count())->toBe(2);
});

it('has no PIN when the password is null', function () {
    $user = User::factory()->create();

    expect($user->password)->toBeNull()
        ->and($user->hasPin())->toBeFalse();
});

it('has a PIN once one is set, stored hashed', function () {
    $user = User::factory()->withPin('1234')->create();

    expect($user->hasPin())->toBeTrue()
        ->and($user->password)->not->toBe('1234')
        ->and(Hash::check('1234', $user->password))->toBeTrue()
        ->and($user->pin_set_at)->not->toBeNull();
});

it('defaults to an active user role', function () {
    $user = User::factory()->create()->fresh();

    expect($user->role)->toBe(Role::User)
        ->and($user->is_active)->toBeTrue()
        ->and($user->isAdmin())->toBeFalse();
});

it('has admin and inactive factory states', function () {
    $admin = User::factory()->admin()->create();
    $inactive = User::factory()->inactive()->create();

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->hasRole(Role::Admin))->toBeTrue()
        ->and($admin->hasPin())->toBeFalse()
        ->and($inactive->is_active)->toBeFalse();
});
