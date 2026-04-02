<?php

namespace App\Filament\Resources\ProductionOrders;

use App\Filament\Resources\ProductionOrders\Pages\CreateProductionOrder;
use App\Filament\Resources\ProductionOrders\Pages\EditProductionOrder;
use App\Filament\Resources\ProductionOrders\Pages\ListProductionOrders;
use App\Filament\Resources\ProductionOrders\Pages\ViewProductionOrder;
use App\Filament\Resources\ProductionOrders\Schemas\ProductionOrderForm;
use App\Filament\Resources\ProductionOrders\Schemas\ProductionOrderInfolist;
use App\Filament\Resources\ProductionOrders\Tables\ProductionOrdersTable;
use App\Models\Manufacturing\ProductionOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionOrderResource extends Resource
{
    protected static ?string $model = ProductionOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ProductionOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductionOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionOrdersTable::configure($table);
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
            'index' => ListProductionOrders::route('/'),
            'create' => CreateProductionOrder::route('/create'),
            'view' => ViewProductionOrder::route('/{record}'),
            'edit' => EditProductionOrder::route('/{record}/edit'),
        ];
    }
}
