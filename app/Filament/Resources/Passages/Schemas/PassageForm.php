<?php

namespace App\Filament\Resources\Passages\Schemas;

use App\Enums\Language;
use App\Filament\Forms\MediaRepeater;
use App\Filament\Forms\TaxonomyFields;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A passage holds text and/or images (FR-Q3). Its items always share its subtest and topic.
 */
class PassageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Classification'))
                    ->description(__('Changing these also moves every item in this passage.'))
                    ->columns(3)
                    ->schema([
                        TaxonomyFields::subtest(),
                        TaxonomyFields::topic(),
                        Select::make('source_id')
                            ->label(__('Source'))
                            ->relationship('source', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                Section::make(__('Passage'))
                    ->schema([
                        TextInput::make('title')->label(__('Title'))->maxLength(255),
                        MarkdownEditor::make('body')
                            ->label(__('Text'))
                            ->helperText(__('Markdown. Tables are supported. A passage needs text or an image before its items can be published.'))
                            ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList', 'table'], ['undo', 'redo']]),
                        Select::make('language')->label(__('Language'))->options(Language::class)->default(Language::En)->required(),
                    ]),
                MediaRepeater::section(),
            ]);
    }
}
