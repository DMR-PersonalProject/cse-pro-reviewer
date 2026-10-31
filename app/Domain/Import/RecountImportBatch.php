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
        $counts = $batch->rows()->reorder()
            ->selectRaw('decision, count(*) as aggregate')
            ->groupBy('decision')
            ->pluck('aggregate', 'decision');

        $batch->forceFill([
            'approved_rows' => (int) ($counts[ImportRowDecision::Approved->value] ?? 0),
            'rejected_rows' => (int) ($counts[ImportRowDecision::Rejected->value] ?? 0),
            'status' => ($counts[ImportRowDecision::Pending->value] ?? 0) > 0
                ? ImportBatchStatus::Pending
                : ImportBatchStatus::Completed,
        ])->save();
    }
}
