<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates a user account from the start page (FR-I1, FR-I2).
 */
class RegisterUser
{
    /**
     * @param  array{username: string, ign: string, full_name: string, pin?: ?string, pin_confirmation?: ?string}  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input): User
    {
        $input = $this->normalize($input);
        $data = $this->validate($input);

        try {
            return User::create([
                'username' => $data['username'],
                'ign' => $data['ign'],
                'full_name' => $data['full_name'],
                'password' => $data['pin'] ?: null,
                'pin_set_at' => $data['pin'] ? now() : null,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Someone took the username or IGN between validation and insert: report it as "taken".
            $this->validate($input);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        return [
            'username' => mb_strtolower(trim((string) ($input['username'] ?? ''))),
            'ign' => trim((string) ($input['ign'] ?? '')),
            'full_name' => trim((string) ($input['full_name'] ?? '')),
            'pin' => (string) ($input['pin'] ?? ''),
            'pin_confirmation' => (string) ($input['pin_confirmation'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(array $data): array
    {
        return Validator::make($data, [
            'username' => ['required', 'string', 'regex:/^[a-z0-9_]{3,20}$/', 'unique:users,username'],
            'ign' => ['required', 'string', 'regex:/^[\p{L}\p{N} _-]{3,20}$/u', $this->ignIsFree(...)],
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'pin' => ['nullable', 'digits_between:4,6', 'confirmed'],
        ], [
            'username.regex' => __('Use 3–20 lowercase letters, digits or underscores.'),
            'username.unique' => __('Username is taken'),
            'ign.regex' => __('Use 3–20 letters, digits, spaces, underscores or dashes.'),
            'pin.digits_between' => __('The PIN must be 4–6 digits.'),
            'pin.confirmed' => __('The PINs don’t match.'),
        ], [
            'ign' => 'IGN',
            'full_name' => __('full name'),
            'pin' => 'PIN',
        ])->validate();
    }

    private function ignIsFree(string $attribute, mixed $value, Closure $fail): void
    {
        if (User::where('ign_normalized', mb_strtolower((string) $value))->exists()) {
            $fail(__('IGN is taken'));
        }
    }
}
