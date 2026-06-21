<?php

namespace App\Filament\Resources\ItemLots;

use App\Filament\Resources\ItemLots\Pages\CreateItemLot;
use App\Filament\Resources\ItemLots\Pages\EditItemLot;
use App\Filament\Resources\ItemLots\Pages\ListItemLots;
use App\Filament\Resources\ItemLots\Pages\ViewItemLot;
use App\Filament\Resources\ItemLots\Schemas\ItemLotForm;
use App\Filament\Resources\ItemLots\Schemas\ItemLotInfolist;
use App\Filament\Resources\ItemLots\Tables\ItemLotsTable;
use App\Models\ItemLot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ItemLotResource extends Resource
{
    protected static ?string $model = ItemLot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ItemLotForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemLotInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemLotsTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof ItemLot) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'lot_number',
            'supplier_lot',
            'coa_reference',
            'status',
            'item.item_code',
            'item.description',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var ItemLot $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ItemLot $record */
        return [
            'Item' => $record->item
                ? "{$record->item->item_code} - {$record->item->description}"
                : '—',
            'Supplier Lot' => $record->supplier_lot ?: '—',
            'Status' => $record->status ?: '—',
            'Remaining' => number_format((float) $record->quantity_remaining, 2),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('item');
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
            'index' => ListItemLots::route('/'),
            'create' => CreateItemLot::route('/create'),
            'view' => ViewItemLot::route('/{record}'),
            'edit' => EditItemLot::route('/{record}/edit'),
        ];
    }

    protected static function formatRecordTitle(ItemLot $record): string
    {
        $lotNumber = $record->lot_number ?: 'Unknown Lot';
        $itemCode = $record->item?->item_code ?: 'Item';

        return "{$lotNumber} - {$itemCode}";
    }
}
