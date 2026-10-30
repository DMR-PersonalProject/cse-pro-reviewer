<?php

namespace App\Filament\Resources\Sources\Pages;

use App\Domain\Content\DeleteSource;
use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Resources\Sources\SourceResource;
use App\Models\Source;
use Filament\Resources\Pages\EditRecord;

/**
 * Deleting a source removes its items and passages, and needs the name typed in (M2-11).
 */
class EditSource extends EditRecord
{
    protected static string $resource = SourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TypedDeleteAction::make(
                expected: fn (Source $record) => $record->name,
                delete: fn (Source $record, string $typed) => app(DeleteSource::class)->handle($record, $typed),
                what: __('the source name'),
                redirectTo: SourceResource::getUrl('index'),
            ),
        ];
    }
}
