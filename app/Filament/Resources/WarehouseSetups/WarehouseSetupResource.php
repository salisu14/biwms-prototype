<?php

namespace App\Filament\Resources\WarehouseSetups;

use App\Filament\Resources\WarehouseSetups\Pages\CreateWarehouseSetup;
use App\Filament\Resources\WarehouseSetups\Pages\EditWarehouseSetup;
use App\Filament\Resources\WarehouseSetups\Pages\ListWarehouseSetups;
use App\Filament\Resources\WarehouseSetups\Pages\ViewWarehouseSetup;
use App\Filament\Resources\WarehouseSetups\Schemas\WarehouseSetupForm;
use App\Filament\Resources\WarehouseSetups\Schemas\WarehouseSetupInfolist;
use App\Filament\Resources\WarehouseSetups\Tables\WarehouseSetupsTable;
use App\Models\WarehouseSetup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WarehouseSetupResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'warehouse';
    }

    public static function permissionResource(): string
    {
        return 'warehouse_setup';
    }

    protected static ?string $model = WarehouseSetup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;

    protected static string|null|\UnitEnum $navigationGroup = 'Warehouse';

    protected static ?string $navigationLabel = 'Warehouse Setup';

    protected static ?string $label = 'Warehouse Setup';

    protected static ?string $pluralLabel = 'Warehouse Setup';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return WarehouseSetupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseSetupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehouseSetupsTable::configure($table);
    }

    // Only one record allowed — BC style
    public static function canCreate(): bool
    {
        return static::getModel()::count() === 0;
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
            'index' => ListWarehouseSetups::route('/'),
            'create' => CreateWarehouseSetup::route('/create'),
            'view' => ViewWarehouseSetup::route('/{record}'),
            'edit' => EditWarehouseSetup::route('/{record}/edit'),
        ];
    }
}
