<?php

namespace App\Filament\Resources\ImportBatches\Tables;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportFormat;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->description(fn ($record) => $record->original_filename)
                    ->searchable(['name', 'original_filename']),
                TextColumn::make('format')->label(__('Format'))->badge(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (ImportBatchStatus $state) => $state === ImportBatchStatus::Completed ? 'success' : 'warning'),
                TextColumn::make('total_rows')->label(__('Rows'))->numeric(),
                TextColumn::make('valid_rows')->label(__('Without errors'))->numeric(),
                TextColumn::make('approved_rows')->label(__('Approved'))->numeric(),
                TextColumn::make('rejected_rows')->label(__('Rejected'))->numeric(),
                TextColumn::make('user.ign')->label(__('Uploaded by'))->placeholder(__('Console')),
                TextColumn::make('created_at')->label(__('Uploaded'))->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(ImportBatchStatus::class),
                SelectFilter::make('format')->label(__('Format'))->options(ImportFormat::class),
            ])
            ->recordActions([
                ViewAction::make()->label(__('Review')),
            ]);
    }
}
