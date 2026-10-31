<?php

namespace App\Filament\Resources\ImportBatches;

use App\Enums\ImportBatchStatus;
use App\Filament\Resources\ImportBatches\Pages\ImportWizard;
use App\Filament\Resources\ImportBatches\Pages\ListImportBatches;
use App\Filament\Resources\ImportBatches\Pages\ViewImportBatch;
use App\Filament\Resources\ImportBatches\RelationManagers\RowsRelationManager;
use App\Filament\Resources\ImportBatches\Tables\ImportBatchesTable;
use App\Models\ImportBatch;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Upload JSON or CSV, preview each row's errors and flags, then approve or reject rows (FR-M2).
 */
class ImportBatchResource extends Resource
{
    protected static ?string $model = ImportBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Question bank');
    }

    public static function getModelLabel(): string
    {
        return __('import');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Imports');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Warnings'))
                ->visible(fn (ImportBatch $record) => ($record->meta['warnings'] ?? []) !== [])
                ->schema([
                    TextEntry::make('meta.warnings')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->color('warning'),
                ])
                ->columnSpanFull(),
            Section::make(__('Batch'))
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('original_filename')->label(__('File')),
                    TextEntry::make('format')->label(__('Format'))->badge(),
                    TextEntry::make('status')->label(__('Status'))->badge()
                        ->color(fn (ImportBatchStatus $state) => $state === ImportBatchStatus::Completed ? 'success' : 'warning'),
                    TextEntry::make('defaultSource.name')->label(__('Source'))
                        ->placeholder(__('Created on first approval (private)')),
                    TextEntry::make('total_rows')->label(__('Rows')),
                    TextEntry::make('valid_rows')->label(__('Without errors')),
                    TextEntry::make('approved_rows')->label(__('Approved')),
                    TextEntry::make('rejected_rows')->label(__('Rejected')),
                    TextEntry::make('user.ign')->label(__('Uploaded by'))->placeholder(__('Console')),
                    TextEntry::make('created_at')->label(__('Uploaded'))->dateTime(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return ImportBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RowsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportBatches::route('/'),
            'upload' => ImportWizard::route('/upload'),
            'view' => ViewImportBatch::route('/{record}'),
        ];
    }
}
