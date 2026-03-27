<?php

namespace App\Filament\Resources\ItemLedgers;

use App\Filament\Resources\ItemLedgers\Pages\CreateItemLedger;
use App\Filament\Resources\ItemLedgers\Pages\EditItemLedger;
use App\Filament\Resources\ItemLedgers\Pages\ListItemLedgers;
use App\Filament\Resources\ItemLedgers\Pages\ViewItemLedger;
use App\Filament\Resources\ItemLedgers\Schemas\ItemLedgerForm;
use App\Filament\Resources\ItemLedgers\Schemas\ItemLedgerInfolist;
use App\Filament\Resources\ItemLedgers\Tables\ItemLedgersTable;
use App\Models\ItemLedger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ItemLedgerResource extends Resource
{
    protected static ?string $model = ItemLedger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ItemLedgerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemLedgerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemLedgersTable::configure($table);
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
            'index' => ListItemLedgers::route('/'),
            'create' => CreateItemLedger::route('/create'),
            'view' => ViewItemLedger::route('/{record}'),
            'edit' => EditItemLedger::route('/{record}/edit'),
        ];
    }
}
