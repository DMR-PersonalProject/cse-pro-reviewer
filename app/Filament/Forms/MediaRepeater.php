<?php

namespace App\Filament\Forms;

use App\Domain\Content\AttachMedia;
use App\Domain\Content\MediaValidator;
use App\Rules\ValidImage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Images on a question or passage: PNG, JPG or WebP up to 2 MB, real type checked by
 * MediaValidator, random stored names, required alt text (FR-Q1, AC-21a).
 */
class MediaRepeater
{
    public static function section(): Section
    {
        return Section::make(__('Images'))
            ->description(__('PNG, JPG or WebP, up to 2 MB each. Alt text is required.'))
            ->collapsible()
            ->schema([self::make()]);
    }

    public static function make(): Repeater
    {
        return Repeater::make('media')
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
            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => [...$data, ...app(AttachMedia::class)->metadataFor($data['path'], $data['alt_text'])]);
    }
}
