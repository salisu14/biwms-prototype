<?php

namespace App\Filament\Resources\ProductionBomVersions;

use App\Filament\Resources\ProductionBomVersions\Pages\CreateProductionBomVersion;
use App\Filament\Resources\ProductionBomVersions\Pages\EditProductionBomVersion;
use App\Filament\Resources\ProductionBomVersions\Pages\ListProductionBomVersions;
use App\Filament\Resources\ProductionBomVersions\Pages\ViewProductionBomVersion;
use App\Filament\Resources\ProductionBomVersions\Schemas\ProductionBomVersionForm;
use App\Filament\Resources\ProductionBomVersions\Schemas\ProductionBomVersionInfolist;
use App\Filament\Resources\ProductionBomVersions\Tables\ProductionBomVersionsTable;
use App\Models\Manufacturing\ProductionBomVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionBomVersionResource extends Resource
{
    protected static ?string $model = ProductionBomVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return ProductionBomVersionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductionBomVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionBomVersionsTable::configure($table);
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
            'index' => ListProductionBomVersions::route('/'),
            'create' => CreateProductionBomVersion::route('/create'),
            'view' => ViewProductionBomVersion::route('/{record}'),
            'edit' => EditProductionBomVersion::route('/{record}/edit'),
        ];
    }
}
