<?php

namespace App\Filament\Resources\Sources\Schemas;

use App\Enums\SourceType;
use App\Enums\SourceVisibility;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label(__('Type'))
                    ->options(SourceType::class)
                    ->required(),
                Select::make('visibility')
                    ->label(__('Visibility'))
                    ->options(SourceVisibility::class)
                    ->default(SourceVisibility::default())
                    ->helperText(__('Private items are visible to admins only and never count toward the leaderboard.'))
                    ->required(),
                Textarea::make('notes')
                    ->label(__('Notes'))
                    ->columnSpanFull(),
            ]);
    }
}
