<?php

namespace App\Filament\Resources\ProductionBoms;

use App\Filament\Resources\ProductionBoms\Pages\CreateProductionBom;
use App\Filament\Resources\ProductionBoms\Pages\EditProductionBom;
use App\Filament\Resources\ProductionBoms\Pages\ListProductionBoms;
use App\Filament\Resources\ProductionBoms\Pages\ViewProductionBom;
use App\Filament\Resources\ProductionBoms\RelationManagers\ProductionBomLinesRelationManager;
use App\Filament\Resources\ProductionBoms\Schemas\ProductionBomForm;
use App\Filament\Resources\ProductionBoms\Schemas\ProductionBomInfolist;
use App\Filament\Resources\ProductionBoms\Tables\ProductionBomsTable;
use App\Models\Manufacturing\ProductionBom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionBomResource extends Resource
{
    protected static ?string $model = ProductionBom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return ProductionBomForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductionBomInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionBomsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProductionBomLinesRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionBoms::route('/'),
            'create' => CreateProductionBom::route('/create'),
            'view' => ViewProductionBom::route('/{record}'),
            'edit' => EditProductionBom::route('/{record}/edit'),
        ];
    }
}
