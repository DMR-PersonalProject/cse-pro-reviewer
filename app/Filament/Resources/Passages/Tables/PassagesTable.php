<?php

namespace App\Filament\Resources\Passages\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PassagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['subtest', 'topic', 'source']))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('title')->label(__('Title'))->placeholder(__('Untitled'))->searchable()->limit(50),
                TextColumn::make('subtest.name')->label(__('Subtest')),
                TextColumn::make('topic.name')->label(__('Topic')),
                TextColumn::make('source.name')->label(__('Source'))->limit(30)->toggleable(),
                TextColumn::make('questions_count')->label(__('Items'))->counts('questions')->sortable(),
                TextColumn::make('updated_at')->label(__('Updated'))->since()->sortable()->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('subtest_id')->label(__('Subtest'))->relationship('subtest', 'name'),
                SelectFilter::make('topic_id')->label(__('Topic'))->relationship('topic', 'name')->searchable()->preload(),
                SelectFilter::make('source_id')->label(__('Source'))->relationship('source', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
