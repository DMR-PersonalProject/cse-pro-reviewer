<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Audit\Audit;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * An admin changes a user's IGN (FR-I1, FR-I6). Uniqueness ignores case, so it is checked on a lowercase copy.
 */
class ChangeIgn
{
    public const PATTERN = '/^[\p{L}\p{N} _-]{3,20}$/u';

    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $ign): void
    {
        $ign = trim($ign);

        Validator::make(
            ['ign' => $ign, 'ign_normalized' => mb_strtolower($ign)],
            [
                'ign' => ['required', 'regex:'.self::PATTERN],
                'ign_normalized' => [Rule::unique('users', 'ign_normalized')->ignore($user->getKey())],
            ],
            ['ign_normalized.unique' => __('That IGN is already taken.')],
            ['ign' => 'IGN', 'ign_normalized' => 'IGN'],
        )->validate();

        $old = $user->ign;
        $user->update(['ign' => $ign]);

        Audit::record('user.ign_changed', $user, meta: ['old' => $old, 'new' => $ign]);
    }
}
