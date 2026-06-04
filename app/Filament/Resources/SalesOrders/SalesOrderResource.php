<?php

namespace App\Filament\Resources\SalesOrders;

use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\EditSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderInfolist;
use App\Filament\Resources\SalesOrders\Tables\SalesOrdersTable;
use App\Models\SalesOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function form(Schema $schema): Schema
    {
        return SalesOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
            RelationManagers\GlEntriesRelationManager::class,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'order_number',
            'external_document_number',
            'customer.name',
            'customer.customer_number',
            'customer_name',
            'ship_to_name',
            'status',
            'location.code',
            'location.name',
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof SalesOrder) {
            return static::getModelLabel();
        }

        $customer = $record->customer?->name ?: $record->customer_name ?: 'Unknown Customer';

        return "{$record->order_number} - {$customer}";
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var SalesOrder $record */
        $customer = $record->customer?->name ?: $record->customer_name ?: 'Unknown Customer';

        return "{$record->order_number} - {$customer}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var SalesOrder $record */
        return [
            'Customer' => $record->customer?->customer_number
                ? "{$record->customer->customer_number} - ".($record->customer?->name ?: $record->customer_name ?: '—')
                : ($record->customer?->name ?: $record->customer_name ?: '—'),
            'Location' => $record->location?->code
                ? "{$record->location->code} - {$record->location->name}"
                : ($record->location?->name ?? '—'),
            'External Doc' => $record->external_document_number ?: '—',
            'Status' => $record->status?->value ?? '—',
            'Total' => number_format((float) $record->grand_total, 2).' '.($record->currency_code ?: ''),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer', 'location']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesOrders::route('/'),
            'create' => CreateSalesOrder::route('/create'),
            'view' => ViewSalesOrder::route('/{record}'),
            'edit' => EditSalesOrder::route('/{record}/edit'),

            'archived' => Pages\ArchivedSalesOrders::route('/history/archived-orders'), // Add
        ];
    }
}
