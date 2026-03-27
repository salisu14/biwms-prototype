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
}
