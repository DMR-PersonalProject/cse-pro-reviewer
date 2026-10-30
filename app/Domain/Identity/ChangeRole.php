<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Audit\Audit;
use App\Enums\Role;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * An admin changes another account's role (FR-I6). Promoting needs an email and an admin
 * password, which replaces any PIN (FR-I5). Demoting clears the password, so the account has
 * no PIN until its owner sets one. Either way the account is signed out everywhere.
 */
class ChangeRole
{
    public function __construct(private readonly EndSessions $endSessions) {}

    /**
     * @return array<string, mixed>
     */
    public static function promotionRules(User $user): array
    {
        return [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @throws DomainException for a self-change, no change, or the last active admin
     * @throws ValidationException when promoting without valid credentials
     */
    public function handle(User $user, Role $role, ?string $email = null, ?string $password = null, ?string $passwordConfirmation = null): void
    {
        if (Auth::user()?->is($user)) {
            throw new DomainException(__('You can’t change your own role.'));
        }

        if ($user->role === $role) {
            throw new DomainException(__('This account already has that role.'));
        }

        if ($role === Role::Admin) {
            Validator::make(
                ['email' => $email, 'password' => $password, 'password_confirmation' => $passwordConfirmation],
                self::promotionRules($user),
            )->validate();

            $changes = [
                'role' => Role::Admin,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $password,
                'pin_set_at' => null,
            ];
        } else {
            LastActiveAdmin::guard($user);

            $changes = ['role' => $role, 'password' => null, 'pin_set_at' => null];
        }

        $old = $user->role;

        DB::transaction(function () use ($user, $changes, $old, $role) {
            $user->forceFill($changes)->save();
            $this->endSessions->handle($user);

            Audit::record('user.role_changed', $user, meta: ['old' => $old->value, 'new' => $role->value]);
        });
    }
}
