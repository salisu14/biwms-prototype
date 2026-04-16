<?php

namespace App\Filament\Resources\InventoryPutaways;

use App\Filament\Resources\InventoryPutaways\Pages\CreateInventoryPutaway;
use App\Filament\Resources\InventoryPutaways\Pages\EditInventoryPutaway;
use App\Filament\Resources\InventoryPutaways\Pages\ListInventoryPutaways;
use App\Filament\Resources\InventoryPutaways\Pages\ViewInventoryPutaway;
use App\Filament\Resources\InventoryPutaways\Schemas\InventoryPutawayForm;
use App\Filament\Resources\InventoryPutaways\Schemas\InventoryPutawayInfolist;
use App\Filament\Resources\InventoryPutaways\Tables\InventoryPutawaysTable;
use App\Models\InventoryPutaway;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventoryPutawayResource extends Resource
{
    protected static ?string $model = InventoryPutaway::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return InventoryPutawayForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryPutawayInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryPutawaysTable::configure($table);
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
            'index' => ListInventoryPutaways::route('/'),
            'create' => CreateInventoryPutaway::route('/create'),
            'view' => ViewInventoryPutaway::route('/{record}'),
            'edit' => EditInventoryPutaway::route('/{record}/edit'),
        ];
    }
}
