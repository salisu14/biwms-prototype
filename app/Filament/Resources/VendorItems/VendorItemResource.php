<?php

namespace App\Filament\Resources\VendorItems;

use App\Filament\Resources\VendorItems\Pages\CreateVendorItem;
use App\Filament\Resources\VendorItems\Pages\EditVendorItem;
use App\Filament\Resources\VendorItems\Pages\ListVendorItems;
use App\Filament\Resources\VendorItems\Pages\ViewVendorItem;
use App\Filament\Resources\VendorItems\Schemas\VendorItemForm;
use App\Filament\Resources\VendorItems\Schemas\VendorItemInfolist;
use App\Filament\Resources\VendorItems\Tables\VendorItemsTable;
use App\Models\VendorItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class VendorItemResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'procurement';
    }

    public static function permissionResource(): string
    {
        return 'vendor_item';
    }

    protected static ?string $model = VendorItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return VendorItemForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorItemsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['vendor', 'item', 'currency', 'purchaseUom']);
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof VendorItem) {
            return static::getModelLabel();
        }

        $vendor = $record->vendor
            ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
            : 'Unknown Vendor';
        $item = $record->item
            ? "{$record->item->item_code} - {$record->item->description}"
            : 'Unknown Item';

        return "{$vendor} - {$item}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'vendor.vendor_code',
            'vendor.vendor_name',
            'item.item_code',
            'item.description',
            'vendor_item_number',
            'vendor_item_name',
            'vendor_item_category',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var VendorItem $record */
        $vendor = $record->vendor
            ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
            : 'Unknown Vendor';
        $item = $record->item
            ? "{$record->item->item_code} - {$record->item->description}"
            : 'Unknown Item';

        return "{$vendor} • {$item}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var VendorItem $record */
        return [
            'Vendor SKU' => $record->vendor_item_number ?: '—',
            'Purchase UoM' => $record->purchaseUom?->uom_code ?: '—',
            'Unit Cost' => Number::currency((float) $record->unit_cost, $record->currency?->code ?? config('app.default_currency', 'USD')),
            'MOQ' => (string) $record->minimum_order_qty,
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['vendor', 'item', 'currency', 'purchaseUom']);
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
            'index' => ListVendorItems::route('/'),
            'create' => CreateVendorItem::route('/create'),
            'view' => ViewVendorItem::route('/{record}'),
            'edit' => EditVendorItem::route('/{record}/edit'),
        ];
    }
}
