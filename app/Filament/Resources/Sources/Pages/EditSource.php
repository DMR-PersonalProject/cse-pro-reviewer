<?php

namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource;
use Filament\Resources\Pages\EditRecord;

/**
 * No plain delete here: deleting a source with its items needs typed confirmation (M2-11).
 */
class EditSource extends EditRecord
{
    protected static string $resource = SourceResource::class;
}
