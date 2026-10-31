<?php

namespace App\Policies;

use App\Models\ImportBatch;
use App\Models\User;

/**
 * Imports are admin-only (FR-M2). Batches are kept as a record; there is no edit or delete.
 */
class ImportBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ImportBatch $batch): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ImportBatch $batch): bool
    {
        return false;
    }

    public function delete(User $user, ImportBatch $batch): bool
    {
        return false;
    }
}
