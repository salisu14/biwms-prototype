<?php

namespace App\Filament\Resources\FAClasses;

use App\Filament\Resources\FAClasses\Pages\CreateFAClass;
use App\Filament\Resources\FAClasses\Pages\EditFAClass;
use App\Filament\Resources\FAClasses\Pages\ListFAClasses;
use App\Filament\Resources\FAClasses\Schemas\FAClassForm;
use App\Filament\Resources\FAClasses\Tables\FAClassesTable;
use App\Models\FAClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FAClassResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'fixed_asset';
    }

    public static function permissionResource(): string
    {
        return 'f_a_class';
    }

    protected static ?string $model = FAClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FAClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FAClassesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubclassesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFAClasses::route('/'),
            'create' => CreateFAClass::route('/create'),
            'edit' => EditFAClass::route('/{record}/edit'),
        ];
    }
}
