<?php

namespace App\Policies;

use App\Models\ImportRow;
use App\Models\User;

/**
 * Rows are decided through ApproveImportRows and RejectImportRows, never edited directly.
 */
class ImportRowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ImportRow $row): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ImportRow $row): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ImportRow $row): bool
    {
        return false;
    }
}
