<?php

namespace App\Filament\Resources\MaintenanceContractAssets;

use App\Filament\Resources\MaintenanceContractAssets\Pages\CreateMaintenanceContractAsset;
use App\Filament\Resources\MaintenanceContractAssets\Pages\EditMaintenanceContractAsset;
use App\Filament\Resources\MaintenanceContractAssets\Pages\ListMaintenanceContractAssets;
use App\Filament\Resources\MaintenanceContractAssets\Schemas\MaintenanceContractAssetForm;
use App\Filament\Resources\MaintenanceContractAssets\Tables\MaintenanceContractAssetsTable;
use App\Models\MaintenanceContractAsset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MaintenanceContractAssetResource extends Resource
{
    protected static ?string $model = MaintenanceContractAsset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return MaintenanceContractAssetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceContractAssetsTable::configure($table);
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
            'index' => ListMaintenanceContractAssets::route('/'),
            'create' => CreateMaintenanceContractAsset::route('/create'),
            'edit' => EditMaintenanceContractAsset::route('/{record}/edit'),
        ];
    }
}
