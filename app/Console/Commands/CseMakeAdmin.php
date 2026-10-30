<?php

namespace App\Console\Commands;

use App\Domain\Audit\Audit;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates an admin account. There are no default credentials anywhere (A-18).
 */
class CseMakeAdmin extends Command
{
    protected $signature = 'cse:make-admin';

    protected $description = 'Create an admin account for the /admin panel';

    public function handle(): int
    {
        $input = [
            'email' => trim(text('Email', required: true)),
            'username' => mb_strtolower(trim(text('Username', required: true))),
            'ign' => trim(text('IGN', required: true)),
            'full_name' => trim(text('Full name', required: true)),
            'password' => password('Password', required: true),
            'password_confirmation' => password('Confirm password', required: true),
        ];

        // IGN uniqueness ignores case, so it is checked on a lowercase copy.
        $validator = Validator::make([...$input, 'ign_normalized' => mb_strtolower($input['ign'])], [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'regex:/^[a-z0-9_]{3,20}$/', 'unique:users,username'],
            'ign' => ['required', 'regex:/^[\p{L}\p{N} _-]{3,20}$/u'],
            'ign_normalized' => ['unique:users,ign_normalized'],
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], attributes: ['ign_normalized' => 'IGN']);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = User::forceCreate([
            'email' => $input['email'],
            'email_verified_at' => now(),
            'username' => $input['username'],
            'ign' => $input['ign'],
            'full_name' => $input['full_name'],
            'password' => $input['password'],
            'role' => Role::Admin,
            'is_active' => true,
        ]);

        Audit::record('user.admin_created', $admin);

        $this->components->info("Admin {$admin->email} created. Sign in at ".url('/admin').'.');

        return self::SUCCESS;
    }
}
