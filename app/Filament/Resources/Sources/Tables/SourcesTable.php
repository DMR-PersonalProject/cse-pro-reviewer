<?php

namespace App\Filament\Resources\Sources\Tables;

use App\Enums\SourceType;
use App\Enums\SourceVisibility;
use App\Models\Source;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('visibility')
                    ->label(__('Visibility'))
                    ->badge()
                    ->color(fn (SourceVisibility $state) => $state === SourceVisibility::Public ? 'success' : 'gray'),
                TextColumn::make('questions_count')
                    ->label(__('Items'))
                    ->counts('questions')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('visibility')->label(__('Visibility'))->options(SourceVisibility::class),
                SelectFilter::make('type')->label(__('Type'))->options(SourceType::class),
            ])
            ->recordActions([
                Action::make('toggleVisibility')
                    ->label(fn (Source $record) => $record->isPublic() ? __('Make private') : __('Make public'))
                    ->icon('heroicon-o-eye')
                    ->requiresConfirmation()
                    ->action(fn (Source $record) => $record->update([
                        'visibility' => $record->isPublic() ? SourceVisibility::Private : SourceVisibility::Public,
                    ])),
                EditAction::make(),
            ]);
    }
}
