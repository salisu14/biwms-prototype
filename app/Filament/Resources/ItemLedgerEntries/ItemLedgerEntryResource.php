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
}
