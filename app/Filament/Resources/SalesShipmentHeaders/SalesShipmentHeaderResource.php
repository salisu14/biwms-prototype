<?php

namespace App\Filament\Resources\SalesShipmentHeaders;

use App\Filament\Resources\SalesShipmentHeaders\Pages\CreateSalesShipmentHeader;
use App\Filament\Resources\SalesShipmentHeaders\Pages\EditSalesShipmentHeader;
use App\Filament\Resources\SalesShipmentHeaders\Pages\ListSalesShipmentHeaders;
use App\Filament\Resources\SalesShipmentHeaders\Pages\ViewSalesShipmentHeader;
use App\Filament\Resources\SalesShipmentHeaders\Schemas\SalesShipmentHeaderForm;
use App\Filament\Resources\SalesShipmentHeaders\Schemas\SalesShipmentHeaderInfolist;
use App\Filament\Resources\SalesShipmentHeaders\Tables\SalesShipmentHeadersTable;
use App\Models\SalesShipmentHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesShipmentHeaderResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'sales_shipment_header';
    }

    protected static ?string $model = SalesShipmentHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SalesShipmentHeaderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesShipmentHeaderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesShipmentHeadersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer', 'salesOrder', 'location']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof SalesShipmentHeader) {
            return static::getModelLabel();
        }

        $customer = $record->sell_to_customer_name ?? 'Unknown Customer';

        return "{$record->document_no} - {$customer}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_no',
            'order_no',
            'sell_to_customer_no',
            'sell_to_customer_name',
            'shipment_date',
            'shipment_method_code',
            'location_code',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var SalesShipmentHeader $record */
        $customer = $record->sell_to_customer_name ?? 'Unknown Customer';

        return "{$record->document_no} - {$customer}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var SalesShipmentHeader $record */
        return [
            'Customer' => $record->sell_to_customer_no
                ? "{$record->sell_to_customer_no} - ".($record->sell_to_customer_name ?? '—')
                : ($record->sell_to_customer_name ?? '—'),
            'Sales Order' => $record->order_no ?: '—',
            'Shipment Date' => $record->shipment_date?->format('d/m/Y') ?? '—',
            'Method' => $record->shipment_method_code ?: '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer', 'salesOrder', 'location']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesShipmentHeaders::route('/'),
            'create' => CreateSalesShipmentHeader::route('/create'),
            'view' => ViewSalesShipmentHeader::route('/{record}'),
            'edit' => EditSalesShipmentHeader::route('/{record}/edit'),

            'posted' => Pages\PostedShipments::route('/history/posted-shipments'),
        ];
    }
}
