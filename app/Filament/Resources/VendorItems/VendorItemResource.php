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

class VendorItemResource extends Resource
{
    protected static ?string $model = VendorItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
