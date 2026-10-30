<?php

use App\Models\User;

it('lets only admins manage users', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect($admin->can('viewAny', User::class))->toBeTrue()
        ->and($admin->can('update', $other))->toBeTrue()
        ->and($admin->can('delete', $other))->toBeTrue()
        ->and($user->can('viewAny', User::class))->toBeFalse()
        ->and($user->can('update', $other))->toBeFalse()
        ->and($user->can('delete', $other))->toBeFalse();
});

it('lets a user view their own account only', function () {
    $user = User::factory()->create();

    expect($user->can('view', $user))->toBeTrue()
        ->and($user->can('view', User::factory()->create()))->toBeFalse();
});

it('does not let an admin delete their own account', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('delete', $admin))->toBeFalse();
});
