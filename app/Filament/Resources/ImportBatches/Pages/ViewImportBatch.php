<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Domain\Import\ApproveImportRows;
use App\Domain\Import\RejectImportRows;
use App\Enums\ImportRowDecision;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Step 2 of an import: the batch summary and its rows (RowsRelationManager).
 *
 * @property ImportBatch $record
 */
class ViewImportBatch extends ViewRecord
{
    protected static string $resource = ImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveValid')
                ->label(__('Approve all rows without errors'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('Items arrive as draft, or needs review when flagged. Nothing is published.'))
                ->visible(fn () => $this->pendingValidRowIds() !== [])
                ->action(function (ApproveImportRows $approve) {
                    $result = $approve->handle($this->record, $this->pendingValidRowIds());

                    Notification::make()->success()
                        ->title(__('Approved :count rows.', ['count' => $result->approved]))
                        ->send();
                }),
            // Error rows can't be selected in the table, so this is how they get decided.
            Action::make('rejectErrors')
                ->label(__('Reject all rows with errors'))
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $this->pendingRowIds(withErrors: true) !== [])
                ->action(function (RejectImportRows $reject) {
                    $count = $reject->handle($this->record, $this->pendingRowIds(withErrors: true));

                    Notification::make()->title(__('Rejected :count rows.', ['count' => $count]))->send();
                }),
        ];
    }

    /**
     * @return list<int>
     */
    private function pendingValidRowIds(): array
    {
        return $this->pendingRowIds(withErrors: false);
    }

    /**
     * @return list<int>
     */
    private function pendingRowIds(bool $withErrors): array
    {
        return $this->record->rows()
            ->where('decision', ImportRowDecision::Pending)
            ->get()
            ->filter(fn (ImportRow $row) => $row->hasErrors() === $withErrors)
            ->modelKeys();
    }
}
