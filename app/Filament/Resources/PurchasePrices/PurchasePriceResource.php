<?php

namespace App\Filament\Resources\PurchasePrices;

use App\Filament\Resources\PurchasePrices\Pages\CreatePurchasePrice;
use App\Filament\Resources\PurchasePrices\Pages\EditPurchasePrice;
use App\Filament\Resources\PurchasePrices\Pages\ListPurchasePrices;
use App\Filament\Resources\PurchasePrices\Pages\ViewPurchasePrice;
use App\Filament\Resources\PurchasePrices\Schemas\PurchasePriceForm;
use App\Filament\Resources\PurchasePrices\Schemas\PurchasePriceInfolist;
use App\Filament\Resources\PurchasePrices\Tables\PurchasePricesTable;
use App\Models\PurchasePrice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class PurchasePriceResource extends Resource
{
    protected static ?string $model = PurchasePrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return PurchasePriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchasePriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchasePricesTable::configure($table);
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
            'index' => ListPurchasePrices::route('/'),
            'create' => CreatePurchasePrice::route('/create'),
            'view' => ViewPurchasePrice::route('/{record}'),
            'edit' => EditPurchasePrice::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof PurchasePrice) {
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
            'vendor_item_no',
            'unit_of_measure_code',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var PurchasePrice $record */
        return [
            'Vendor' => $record->vendor
                ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
                : '—',
            'Item' => $record->item
                ? "{$record->item->item_code} - {$record->item->description}"
                : '—',
            'Unit Cost' => Number::currency((float) $record->direct_unit_cost, $record->currency_code ?? config('app.default_currency', 'USD')),
            'Min Qty' => (string) $record->minimum_quantity,
            'UoM' => $record->unit_of_measure_code ?: 'Base',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['vendor', 'item']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var PurchasePrice $record */
        $vendor = $record->vendor
            ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
            : 'Unknown Vendor';
        $item = $record->item
            ? "{$record->item->item_code} - {$record->item->description}"
            : 'Unknown Item';

        return "{$vendor} • {$item}";
    }
}
