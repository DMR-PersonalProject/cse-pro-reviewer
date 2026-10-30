<?php

namespace App\Filament\Resources\Passages\RelationManagers;

use App\Enums\QuestionStatus;
use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Question;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The passage's items in authored order; drag to reorder (passage_position). New items are
 * written in the full question editor, which opens with this passage selected.
 */
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Items');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('stem')
            ->reorderable('passage_position')
            ->defaultSort('passage_position')
            ->columns([
                TextColumn::make('passage_position')->label('#'),
                TextColumn::make('stem')->label(__('Stem'))->limit(80)->wrap(),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->headerActions([
                Action::make('createItem')
                    ->label(__('New item in this passage'))
                    ->icon('heroicon-o-plus')
                    ->url(fn () => QuestionResource::getUrl('create', ['passage_id' => $this->getOwnerRecord()->getKey()])),
                AssociateAction::make()
                    ->label(__('Add an existing item'))
                    ->recordSelectSearchColumns(['id', 'stem'])
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereNull('passage_id')
                        ->where('status', '!=', QuestionStatus::Archived)),
            ])
            ->recordActions([
                EditAction::make()->url(fn (Question $record) => QuestionResource::getUrl('edit', ['record' => $record])),
                DissociateAction::make()->label(__('Remove from passage')),
            ]);
    }
}
