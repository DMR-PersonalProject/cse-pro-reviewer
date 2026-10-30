<?php

namespace App\Filament\Resources\Questions\Tables;

use App\Enums\QuestionStatus;
use App\Enums\SourceVisibility;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['subtest', 'topic', 'source']))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('stem')->label(__('Stem'))->limit(70)->searchable()->wrap(),
                TextColumn::make('subtest.name')->label(__('Subtest'))->toggleable(),
                TextColumn::make('topic.name')->label(__('Topic'))->toggleable(),
                TextColumn::make('status')->label(__('Status'))->badge()->color(fn (QuestionStatus $state) => match ($state) {
                    QuestionStatus::Published => 'success',
                    QuestionStatus::NeedsReview => 'warning',
                    QuestionStatus::Archived => 'gray',
                    QuestionStatus::Draft => 'info',
                }),
                TextColumn::make('source.name')->label(__('Source'))->limit(30)->toggleable(),
                TextColumn::make('source.visibility')->label(__('Visibility'))->badge()->toggleable(),
                IconColumn::make('duplicate_of_question_id')
                    ->label(__('Duplicate?'))
                    ->icon(fn ($state) => $state ? 'heroicon-o-exclamation-triangle' : null)
                    ->color('warning')
                    ->tooltip(fn ($state) => $state ? __('Possibly a duplicate of #:id', ['id' => $state]) : null),
                TextColumn::make('updated_at')->label(__('Updated'))->since()->sortable()->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('subtest_id')->label(__('Subtest'))->relationship('subtest', 'name'),
                SelectFilter::make('topic_id')->label(__('Topic'))->relationship('topic', 'name')->searchable()->preload(),
                SelectFilter::make('status')->label(__('Status'))->options(QuestionStatus::class),
                SelectFilter::make('source_id')->label(__('Source'))->relationship('source', 'name')->searchable()->preload(),
                SelectFilter::make('visibility')
                    ->label(__('Visibility'))
                    ->options(SourceVisibility::class)
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereHas('source', fn (Builder $source) => $source->where('visibility', $data['value']))
                        : $query),
                Filter::make('needs_review')
                    ->label(__('Needs review'))
                    ->query(fn (Builder $query) => $query->where('status', QuestionStatus::NeedsReview)),
                Filter::make('possible_duplicate')
                    ->label(__('Possible duplicate'))
                    ->query(fn (Builder $query) => $query->whereNotNull('duplicate_of_question_id')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
