<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderInfolist;
use App\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\PurchaseOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class PurchaseOrderResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'procurement';
    }

    public static function permissionResource(): string
    {
        return 'purchase_order';
    }

    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    protected static ?int $globalSearchSort = -295;

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['vendor', 'location']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //            RelationManagers\LinesRelationManager::class,
            RelationManagers\PurchaseOrderLinesRelationManager::class,
            RelationManagers\GlEntriesRelationManager::class,
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof PurchaseOrder) {
            return static::getModelLabel();
        }

        $vendor = $record->vendor?->vendor_name ?? $record->vendor_name ?? 'Unknown Vendor';

        return "{$record->order_number} - {$vendor}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'order_number',
            'vendor.vendor_name',
            'vendor.vendor_code',
            'vendor_name',
            'status',
            'location.code',
            'location.name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var PurchaseOrder $record */
        $vendor = $record->vendor?->vendor_name ?? $record->vendor_name ?? 'Unknown Vendor';

        return "{$record->order_number} - {$vendor}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var PurchaseOrder $record */
        return [
            'Vendor' => $record->vendor?->vendor_name ?: $record->vendor_name ?: '—',
            'Location' => $record->location?->code ? "{$record->location->code} - {$record->location->name}" : ($record->location?->name ?? '—'),
            'Status' => $record->status?->value ?? '—',
            'Order Type' => $record->order_type?->value ?? '—',
            'Total' => Number::currency((float) $record->grand_total, $record->currency_code ?: config('app.default_currency', 'USD')),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['vendor', 'location']);
    }

    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $qualifiedOrderNumber = $query->qualifyColumn('order_number');

        $query->orderByRaw(
            "case
                when lower({$qualifiedOrderNumber}::text) = lower(?) then 0
                when lower({$qualifiedOrderNumber}::text) like lower(?) then 1
                else 2
            end",
            [$search, "%{$search}%"],
        )->orderByDesc($qualifiedOrderNumber);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),

            'archived' => Pages\ArchivedPurchaseOrders::route('/history/archived-pos'),
        ];
    }
}
