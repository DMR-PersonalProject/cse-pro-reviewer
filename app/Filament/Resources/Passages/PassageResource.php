<?php

namespace App\Filament\Resources\Passages;

use App\Filament\Resources\Passages\Pages\CreatePassage;
use App\Filament\Resources\Passages\Pages\EditPassage;
use App\Filament\Resources\Passages\Pages\ListPassages;
use App\Filament\Resources\Passages\RelationManagers\QuestionsRelationManager;
use App\Filament\Resources\Passages\Schemas\PassageForm;
use App\Filament\Resources\Passages\Tables\PassagesTable;
use App\Models\Passage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PassageResource extends Resource
{
    protected static ?string $model = Passage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Question bank');
    }

    public static function form(Schema $schema): Schema
    {
        return PassageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PassagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPassages::route('/'),
            'create' => CreatePassage::route('/create'),
            'edit' => EditPassage::route('/{record}/edit'),
        ];
    }
}
