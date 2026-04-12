<?php

namespace App\Filament\Resources\WarehouseShipments;

use App\Filament\Resources\WarehouseShipments\Pages\CreateWarehouseShipment;
use App\Filament\Resources\WarehouseShipments\Pages\EditWarehouseShipment;
use App\Filament\Resources\WarehouseShipments\Pages\ListWarehouseShipments;
use App\Filament\Resources\WarehouseShipments\Pages\ViewWarehouseShipment;
use App\Filament\Resources\WarehouseShipments\Schemas\WarehouseShipmentForm;
use App\Filament\Resources\WarehouseShipments\Schemas\WarehouseShipmentInfolist;
use App\Filament\Resources\WarehouseShipments\Tables\WarehouseShipmentsTable;
use App\Models\WarehouseShipment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WarehouseShipmentResource extends Resource
{
    protected static ?string $model = WarehouseShipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WarehouseShipmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseShipmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehouseShipmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseShipments::route('/'),
            'create' => CreateWarehouseShipment::route('/create'),
            'view' => ViewWarehouseShipment::route('/{record}'),
            'edit' => EditWarehouseShipment::route('/{record}/edit'),
        ];
    }
}
