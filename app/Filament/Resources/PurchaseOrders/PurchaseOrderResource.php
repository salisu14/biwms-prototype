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

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
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

        return $record->order_number ?: static::getModelLabel();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'order_number',
            'vendor.vendor_name',
            'vendor.vendor_code',
            'vendor_name',
            'status',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var PurchaseOrder $record */
        return $record->order_number ?: static::getModelLabel();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var PurchaseOrder $record */
        return [
            'Vendor' => $record->vendor?->vendor_name ?: $record->vendor_name ?: '—',
            'Status' => $record->status?->value ?? '—',
            'Order Type' => $record->order_type?->value ?? '—',
            'Total' => number_format((float) $record->grand_total, 2).' '.($record->currency_code ?: ''),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('vendor');
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
