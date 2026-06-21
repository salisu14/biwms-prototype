<?php

namespace App\Filament\Resources\ItemSkus;

use App\Filament\Resources\ItemSkus\Pages\CreateItemSku;
use App\Filament\Resources\ItemSkus\Pages\EditItemSku;
use App\Filament\Resources\ItemSkus\Pages\ListItemSkus;
use App\Filament\Resources\ItemSkus\Pages\ViewItemSku;
use App\Filament\Resources\ItemSkus\Schemas\ItemSkuForm;
use App\Filament\Resources\ItemSkus\Schemas\ItemSkuInfolist;
use App\Filament\Resources\ItemSkus\Tables\ItemSkusTable;
use App\Models\ItemSku;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ItemSkuResource extends Resource
{
    protected static ?string $model = ItemSku::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ItemSkuForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemSkuInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemSkusTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof ItemSku) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'sku_code',
            'barcode',
            'item.item_code',
            'item.description',
            'location.code',
            'location.name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var ItemSku $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ItemSku $record */
        return [
            'Item' => $record->item
                ? "{$record->item->item_code} - {$record->item->description}"
                : '—',
            'Location' => $record->location
                ? "{$record->location->code} - {$record->location->name}"
                : '—',
            'Quantity' => number_format((float) $record->current_quantity, 2),
            'Status' => $record->is_active ? 'Active' : 'Inactive',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['item', 'location']);
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
            'index' => ListItemSkus::route('/'),
            'create' => CreateItemSku::route('/create'),
            'view' => ViewItemSku::route('/{record}'),
            'edit' => EditItemSku::route('/{record}/edit'),
        ];
    }

    protected static function formatRecordTitle(ItemSku $record): string
    {
        $skuCode = $record->sku_code ?: 'Unknown SKU';
        $itemCode = $record->item?->item_code ?: 'Item';
        $locationCode = $record->location?->code ?: 'Location';

        return "{$skuCode} - {$itemCode} @ {$locationCode}";
    }
}
