<?php

namespace App\Filament\Resources\UnitOfMeasures;

use App\Filament\Resources\UnitOfMeasures\Pages\CreateUnitOfMeasure;
use App\Filament\Resources\UnitOfMeasures\Pages\EditUnitOfMeasure;
use App\Filament\Resources\UnitOfMeasures\Pages\ListUnitOfMeasures;
use App\Filament\Resources\UnitOfMeasures\Pages\ViewUnitOfMeasure;
use App\Filament\Resources\UnitOfMeasures\Schemas\UnitOfMeasureForm;
use App\Filament\Resources\UnitOfMeasures\Schemas\UnitOfMeasureInfolist;
use App\Filament\Resources\UnitOfMeasures\Tables\UnitOfMeasuresTable;
use App\Models\UnitOfMeasure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UnitOfMeasureResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'unit_of_measures';
    }

    public static function permissionResource(): string
    {
        return 'unit_of_measure';
    }

    protected static ?string $model = UnitOfMeasure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return UnitOfMeasureForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UnitOfMeasureInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitOfMeasuresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitOfMeasures::route('/'),
            'create' => CreateUnitOfMeasure::route('/create'),
            'view' => ViewUnitOfMeasure::route('/{record}'),
            'edit' => EditUnitOfMeasure::route('/{record}/edit'),
        ];
    }
}
