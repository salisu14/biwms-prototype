<?php

namespace App\Filament\Resources\WarehousePutaways;

use App\Filament\Resources\WarehousePutaways\Pages\CreateWarehousePutaway;
use App\Filament\Resources\WarehousePutaways\Pages\EditWarehousePutaway;
use App\Filament\Resources\WarehousePutaways\Pages\ListWarehousePutaways;
use App\Filament\Resources\WarehousePutaways\Pages\ViewWarehousePutaway;
use App\Filament\Resources\WarehousePutaways\Schemas\WarehousePutawayForm;
use App\Filament\Resources\WarehousePutaways\Schemas\WarehousePutawayInfolist;
use App\Filament\Resources\WarehousePutaways\Tables\WarehousePutawaysTable;
use App\Models\WarehousePutaway;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WarehousePutawayResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'warehouse';
    }

    public static function permissionResource(): string
    {
        return 'warehouse_putaway';
    }

    protected static ?string $model = WarehousePutaway::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WarehousePutawayForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehousePutawayInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehousePutawaysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehousePutaways::route('/'),
            'create' => CreateWarehousePutaway::route('/create'),
            'view' => ViewWarehousePutaway::route('/{record}'),
            'edit' => EditWarehousePutaway::route('/{record}/edit'),
        ];
    }
}
