<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

/**
 * The question bank is admin-only. Status changes go through the publish/archive actions.
 */
class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Question $question): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Question $question): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->isAdmin();
    }
}
