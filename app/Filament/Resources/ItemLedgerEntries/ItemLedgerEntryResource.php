<?php

namespace App\Filament\Resources\ItemLedgerEntries;

use App\Filament\Resources\ItemLedgerEntries\Pages\CreateItemLedgerEntry;
use App\Filament\Resources\ItemLedgerEntries\Pages\EditItemLedgerEntry;
use App\Filament\Resources\ItemLedgerEntries\Pages\ListItemLedgerEntries;
use App\Filament\Resources\ItemLedgerEntries\Pages\ViewItemLedgerEntry;
use App\Filament\Resources\ItemLedgerEntries\Schemas\ItemLedgerEntryForm;
use App\Filament\Resources\ItemLedgerEntries\Schemas\ItemLedgerEntryInfolist;
use App\Filament\Resources\ItemLedgerEntries\Tables\ItemLedgerEntriesTable;
use App\Models\ItemLedgerEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ItemLedgerEntryResource extends Resource
{
    protected static ?string $model = ItemLedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ItemLedgerEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemLedgerEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemLedgerEntriesTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof ItemLedgerEntry) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'entry_number',
            'document_number',
            'entry_type',
            'item.item_code',
            'item.description',
            'location.name',
            'location.code',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var ItemLedgerEntry $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ItemLedgerEntry $record */
        return [
            'Item' => $record->item
                ? "{$record->item->item_code} - {$record->item->description}"
                : '—',
            'Location' => $record->location
                ? "{$record->location->code} - {$record->location->name}"
                : '—',
            'Type' => $record->entry_type->value ?? (string) $record->entry_type,
            'Open' => $record->open ? 'Yes' : 'No',
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
            'index' => ListItemLedgerEntries::route('/'),
            'create' => CreateItemLedgerEntry::route('/create'),
            'view' => ViewItemLedgerEntry::route('/{record}'),
            'edit' => EditItemLedgerEntry::route('/{record}/edit'),
        ];
    }

    protected static function formatRecordTitle(ItemLedgerEntry $record): string
    {
        $itemCode = $record->item?->item_code ?: 'Item';
        $locationCode = $record->location?->code ?: 'Location';

        return "#{$record->entry_number} • {$itemCode} @ {$locationCode}";
    }
}
