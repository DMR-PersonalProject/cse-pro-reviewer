<?php

namespace App\Filament\Resources\Passages\Pages;

use App\Filament\Resources\Passages\PassageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPassages extends ListRecords
{
    protected static string $resource = PassageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
