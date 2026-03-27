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
}
