<?php

namespace App\Filament\Resources\WarehouseEntries;

use App\Filament\Resources\WarehouseEntries\Pages\CreateWarehouseEntry;
use App\Filament\Resources\WarehouseEntries\Pages\EditWarehouseEntry;
use App\Filament\Resources\WarehouseEntries\Pages\ListWarehouseEntries;
use App\Filament\Resources\WarehouseEntries\Pages\ViewWarehouseEntry;
use App\Filament\Resources\WarehouseEntries\Schemas\WarehouseEntryForm;
use App\Filament\Resources\WarehouseEntries\Schemas\WarehouseEntryInfolist;
use App\Filament\Resources\WarehouseEntries\Tables\WarehouseEntriesTable;
use App\Models\WarehouseEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WarehouseEntryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'warehouse';
    }

    public static function permissionResource(): string
    {
        return 'warehouse_entry';
    }

    protected static ?string $model = WarehouseEntry::class;

    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WarehouseEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehouseEntriesTable::configure($table);
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
            'index' => ListWarehouseEntries::route('/'),
            'create' => CreateWarehouseEntry::route('/create'),
            'view' => ViewWarehouseEntry::route('/{record}'),
            'edit' => EditWarehouseEntry::route('/{record}/edit'),
        ];
    }
}
