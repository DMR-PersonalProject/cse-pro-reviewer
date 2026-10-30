<?php

namespace App\Filament\Resources\Questions\Schemas;

use App\Domain\Content\AttachMedia;
use App\Domain\Content\MediaValidator;
use App\Enums\Difficulty;
use App\Enums\Language;
use App\Models\Passage;
use App\Models\Subtest;
use App\Models\Topic;
use App\Rules\ValidImage;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Question editor (FR-Q1). Choices are saved by SaveQuestion; tags and media by their relationships.
 */
class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::classification(),
                self::item(),
                self::details(),
                self::images(),
                self::provenance(),
            ]);
    }

    private static function classification(): Section
    {
        return Section::make(__('Classification'))
            ->columns(3)
            ->schema([
                Select::make('subtest_id')
                    ->label(__('Subtest'))
                    ->options(fn () => Subtest::orderBy('position')->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('topic_id', null))
                    ->disabled(fn (Get $get) => filled($get('passage_id')))
                    ->dehydrated(),
                Select::make('topic_id')
                    ->label(__('Topic'))
                    ->options(fn (Get $get) => Topic::where('subtest_id', $get('subtest_id'))->orderBy('position')->pluck('name', 'id'))
                    ->required()
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                            if (blank($get('passage_id')) && ! Topic::whereKey($value)->where('subtest_id', $get('subtest_id'))->exists()) {
                                $fail(__('Pick a topic from the selected subtest.'));
                            }
                        },
                    ])
                    ->disabled(fn (Get $get) => filled($get('passage_id')))
                    ->dehydrated(),
                Select::make('passage_id')
                    ->label(__('Passage (optional)'))
                    ->options(fn () => Passage::orderByDesc('id')->get()->mapWithKeys(
                        fn (Passage $passage) => [$passage->id => $passage->title ?: __('Passage #:id', ['id' => $passage->id])],
                    ))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        if ($passage = Passage::find($state)) {
                            $set('subtest_id', $passage->subtest_id);
                            $set('topic_id', $passage->topic_id);
                        }
                    })
                    ->helperText(__('An item in a passage uses the passage’s subtest and topic.')),
            ]);
    }

    private static function item(): Section
    {
        return Section::make(__('Item'))
            ->schema([
                MarkdownEditor::make('stem')
                    ->label(__('Stem'))
                    ->required()
                    ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList', 'table'], ['undo', 'redo']]),
                Repeater::make('choices')
                    ->label(__('Choices'))
                    ->schema([
                        Textarea::make('body')->label(__('Choice text'))->required()->rows(1)->autosize(),
                        Toggle::make('is_correct')->label(__('Correct')),
                    ])
                    ->columns(['default' => 1, 'md' => 4])
                    ->defaultItems(4)
                    ->minItems(2)
                    ->maxItems(5)
                    ->reorderable()
                    ->addActionLabel(__('Add choice'))
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
                            $correct = collect($value)->filter(fn ($choice) => (bool) ($choice['is_correct'] ?? false))->count();

                            if ($correct !== 1) {
                                $fail(__('Mark exactly one choice as correct.'));
                            }
                        },
                    ]),
                MarkdownEditor::make('explanation')
                    ->label(__('Explanation'))
                    ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList', 'table'], ['undo', 'redo']]),
            ]);
    }

    private static function details(): Section
    {
        return Section::make(__('Details'))
            ->columns(2)
            ->schema([
                Select::make('difficulty')->label(__('Difficulty'))->options(Difficulty::class),
                Select::make('language')->label(__('Language'))->options(Language::class)->default(Language::En)->required(),
                Select::make('tags')
                    ->label(__('Tags'))
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->createOptionForm([TextInput::make('name')->label(__('Name'))->required()->maxLength(50)->unique('tags', 'name')]),
                Toggle::make('lock_choice_order')
                    ->label(__('Keep choice order'))
                    ->helperText(__('For choices like “All of the above”: choices are not shuffled.')),
            ]);
    }

    private static function images(): Section
    {
        return Section::make(__('Images'))
            ->description(__('PNG, JPG or WebP, up to 2 MB each. Alt text is required.'))
            ->collapsible()
            ->schema([
                Repeater::make('media')
                    ->hiddenLabel()
                    ->relationship()
                    ->orderColumn('position')
                    ->defaultItems(0)
                    ->addActionLabel(__('Add image'))
                    ->columns(2)
                    ->schema([
                        FileUpload::make('path')
                            ->label(__('Image'))
                            ->disk(AttachMedia::DISK)
                            ->directory(AttachMedia::DIRECTORY)
                            ->visibility('public')
                            ->acceptedFileTypes(array_keys(MediaValidator::TYPES))
                            ->maxSize(MediaValidator::MAX_BYTES / 1024)
                            ->rules([new ValidImage])
                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file) => AttachMedia::storageName((string) $file->getMimeType()))
                            ->required(),
                        TextInput::make('alt_text')
                            ->label(__('Alt text'))
                            ->helperText(__('Describe what the image shows, for screen readers.'))
                            ->required()
                            ->maxLength(500),
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => [...$data, ...app(AttachMedia::class)->metadataFor($data['path'], $data['alt_text'])])
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => [...$data, ...app(AttachMedia::class)->metadataFor($data['path'], $data['alt_text'])]),
            ]);
    }

    private static function provenance(): Section
    {
        return Section::make(__('Provenance'))
            ->columns(3)
            ->collapsible()
            ->schema([
                Select::make('source_id')
                    ->label(__('Source'))
                    ->relationship('source', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('source_file')->label(__('File'))->maxLength(255),
                TextInput::make('source_page')->label(__('Page'))->maxLength(50),
                Textarea::make('source_notes')->label(__('Notes'))->columnSpanFull(),
            ]);
    }
}
