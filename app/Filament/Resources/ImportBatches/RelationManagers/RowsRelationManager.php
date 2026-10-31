<?php

namespace App\Filament\Resources\ImportBatches\RelationManagers;

use App\Domain\Import\ApproveImportRows;
use App\Domain\Import\RejectImportRows;
use App\Enums\ImportFlag;
use App\Enums\ImportRowDecision;
use App\Filament\Resources\Questions\QuestionResource;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The import preview: each row's errors and flags, with links to a possible duplicate and to the
 * item an approved row became. Rows with errors can't be selected, so they can't be approved.
 *
 * @method ImportBatch getOwnerRecord()
 */
class RowsRelationManager extends RelationManager
{
    protected static string $relationship = 'rows';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Rows');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('row_number')
            ->paginated([25, 50, 100])
            ->checkIfRecordIsSelectableUsing(fn (ImportRow $record) => $record->decision === ImportRowDecision::Pending && ! $record->hasErrors())
            ->columns([
                TextColumn::make('row_number')->label(__('Row')),
                TextColumn::make('payload.stem')->label(__('Stem'))->limit(80)->wrap(),
                TextColumn::make('errors')
                    ->label(__('Errors'))
                    ->listWithLineBreaks()
                    ->color('danger')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('flags')
                    ->label(__('Flags'))
                    ->state(fn (ImportRow $record) => array_map(
                        fn (array $flag) => ImportFlag::from($flag['code'])->label()
                            .($flag['detail'] === null ? '' : ' — '.$flag['detail']),
                        $record->flags,
                    ))
                    ->listWithLineBreaks()
                    ->color('warning')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('duplicate_of_question_id')
                    ->label(__('Duplicate of'))
                    ->formatStateUsing(fn ($state) => '#'.$state)
                    ->url(fn (ImportRow $record) => $record->duplicate_of_question_id
                        ? QuestionResource::getUrl('edit', ['record' => $record->duplicate_of_question_id])
                        : null)
                    ->placeholder('—'),
                TextColumn::make('decision')->label(__('Decision'))->badge()
                    ->color(fn (ImportRowDecision $state) => match ($state) {
                        ImportRowDecision::Approved => 'success',
                        ImportRowDecision::Rejected => 'gray',
                        ImportRowDecision::Pending => 'warning',
                    }),
                TextColumn::make('question_id')
                    ->label(__('Item'))
                    ->formatStateUsing(fn ($state) => '#'.$state)
                    ->url(fn (ImportRow $record) => $record->question_id
                        ? QuestionResource::getUrl('edit', ['record' => $record->question_id])
                        : null)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('decision')->label(__('Decision'))->options(ImportRowDecision::class),
                TernaryFilter::make('has_errors')
                    ->label(__('Errors'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereJsonLength('errors', '>', 0),
                        false: fn (Builder $query) => $query->whereJsonLength('errors', 0),
                    ),
            ])
            ->toolbarActions([
                BulkAction::make('approve')
                    ->label(__('Approve selected'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('Items arrive as draft, or needs review when flagged. Nothing is published.'))
                    ->action(function (Collection $records, ApproveImportRows $approve) {
                        $result = $approve->handle($this->getOwnerRecord(), $records->modelKeys());

                        Notification::make()->success()
                            ->title(__('Approved :count rows.', ['count' => $result->approved]))
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('reject')
                    ->label(__('Reject selected'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Collection $records, RejectImportRows $reject) {
                        $count = $reject->handle($this->getOwnerRecord(), $records->modelKeys());

                        Notification::make()->title(__('Rejected :count rows.', ['count' => $count]))->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
