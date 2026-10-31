<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Audit\Audit;
use App\Enums\ImportRowDecision;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

/**
 * Marks pending rows as rejected (FR-M2). Nothing is written to the question bank.
 */
class RejectImportRows
{
    public function __construct(private readonly RecountImportBatch $recount) {}

    /**
     * @param  list<int>  $rowIds  rows of this batch; others are ignored
     * @return int the number of rows rejected
     */
    public function handle(ImportBatch $batch, array $rowIds): int
    {
        $rejected = DB::transaction(function () use ($batch, $rowIds) {
            $rows = $batch->rows()->whereKey($rowIds)
                ->where('decision', ImportRowDecision::Pending)
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                return 0;
            }

            $batch->rows()->whereKey($rows->modelKeys())->update(['decision' => ImportRowDecision::Rejected]);
            $this->recount->handle($batch);

            Audit::record('import.rows_rejected', $batch, meta: ['rows' => $rows->pluck('row_number')->values()->all()]);

            return $rows->count();
        });

        $batch->refresh();

        return $rejected;
    }
}
