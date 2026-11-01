<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowDecision;
use App\Models\ImportBatch;

/**
 * Recomputes a batch's decision counts from its rows; the batch is completed once no row is pending.
 */
class RecountImportBatch
{
    public function handle(ImportBatch $batch): void
    {
        $count = fn (ImportRowDecision $decision): int => $batch->rows()->reorder()->where('decision', $decision)->count();

        $batch->forceFill([
            'approved_rows' => $count(ImportRowDecision::Approved),
            'rejected_rows' => $count(ImportRowDecision::Rejected),
            'status' => $count(ImportRowDecision::Pending) > 0
                ? ImportBatchStatus::Pending
                : ImportBatchStatus::Completed,
        ])->save();
    }
}
