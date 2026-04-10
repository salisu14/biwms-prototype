<?php

namespace App\Filament\Resources\Dimensions;

use App\Filament\Resources\Dimensions\Pages\CreateDimension;
use App\Filament\Resources\Dimensions\Pages\EditDimension;
use App\Filament\Resources\Dimensions\Pages\ListDimensions;
use App\Filament\Resources\Dimensions\Pages\ViewDimension;
use App\Filament\Resources\Dimensions\Schemas\DimensionForm;
use App\Filament\Resources\Dimensions\Schemas\DimensionInfolist;
use App\Filament\Resources\Dimensions\Tables\DimensionsTable;
use App\Models\Dimension;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DimensionResource extends Resource
{
    protected static ?string $model = Dimension::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DimensionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DimensionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DimensionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ValuesRelationManager::class,
            RelationManagers\DefaultDimensionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDimensions::route('/'),
            'create' => CreateDimension::route('/create'),
            'view' => ViewDimension::route('/{record}'),
            'edit' => EditDimension::route('/{record}/edit'),
        ];
    }
}
