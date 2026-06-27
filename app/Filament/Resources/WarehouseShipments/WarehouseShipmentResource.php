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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WarehouseShipmentResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'warehouse';
    }

    public static function permissionResource(): string
    {
        return 'warehouse_shipment';
    }

    protected static ?string $model = WarehouseShipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'document_number';

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

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_number',
            'source_document',
            'source_document_number',
            'external_document_number',
            'customer.name',
            'customer.customer_number',
            'shipping_agent_code',
            'status',
            'location.code',
            'location.name',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var WarehouseShipment $record */
        return [
            'Customer' => $record->customer?->name ?: '—',
            'Source Doc' => $record->source_document_number ?: '—',
            'Location' => $record->location?->name ?: '—',
            'Status' => $record->status ?: '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'customer',
            'location',
        ]);
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
