<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A read-only view of the audit log. Entries are written only through Audit::record().
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 95;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('System');
    }

    public static function getModelLabel(): string
    {
        return __('audit entry');
    }

    public static function getNavigationLabel(): string
    {
        return __('Audit log');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label(__('When'))->dateTime(),
            TextEntry::make('user.full_name')->label(__('Who'))->placeholder(__('System')),
            TextEntry::make('action')->label(__('Action'))->badge(),
            TextEntry::make('subject')->label(__('Subject'))->state(fn (AuditLog $record) => self::subject($record))->placeholder('—'),
            TextEntry::make('reason')->label(__('Reason'))->placeholder('—'),
            TextEntry::make('meta')
                ->label(__('Details'))
                ->state(fn (AuditLog $record) => json_encode($record->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                ->fontFamily('mono')
                ->extraAttributes(['class' => 'whitespace-pre-wrap'])
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('When'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.full_name')
                    ->label(__('Who'))
                    ->placeholder(__('System'))
                    ->searchable(),
                TextColumn::make('action')
                    ->label(__('Action'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('subject')
                    ->label(__('Subject'))
                    ->state(fn (AuditLog $record) => self::subject($record))
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->label(__('Reason'))
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort(fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->filters([
                SelectFilter::make('action')
                    ->label(__('Action'))
                    ->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }

    private static function subject(AuditLog $record): ?string
    {
        return $record->subject_type ? class_basename($record->subject_type).' #'.$record->subject_id : null;
    }
}
