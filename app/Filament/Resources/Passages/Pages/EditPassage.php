<?php

namespace App\Filament\Resources\Passages\Pages;

use App\Filament\Resources\Passages\PassageResource;
use Filament\Resources\Pages\EditRecord;

/**
 * No delete action: the spec has no passage delete. A passage goes away with its source (M2-11).
 */
class EditPassage extends EditRecord
{
    protected static string $resource = PassageResource::class;
}
