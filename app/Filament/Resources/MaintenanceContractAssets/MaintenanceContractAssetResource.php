<?php

namespace App\Filament\Resources\MaintenanceContractAssets;

use App\Filament\Resources\MaintenanceContractAssets\Pages\CreateMaintenanceContractAsset;
use App\Filament\Resources\MaintenanceContractAssets\Pages\EditMaintenanceContractAsset;
use App\Filament\Resources\MaintenanceContractAssets\Pages\ListMaintenanceContractAssets;
use App\Filament\Resources\MaintenanceContractAssets\Pages\ViewMaintenanceContractAsset;
use App\Filament\Resources\MaintenanceContractAssets\Schemas\MaintenanceContractAssetForm;
use App\Filament\Resources\MaintenanceContractAssets\Schemas\MaintenanceContractAssetInfolist;
use App\Filament\Resources\MaintenanceContractAssets\Tables\MaintenanceContractAssetsTable;
use App\Models\MaintenanceContractAsset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaintenanceContractAssetResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'maintenance_contract_assets';
    }

    public static function permissionResource(): string
    {
        return 'maintenance_contract_asset';
    }

    protected static ?string $model = MaintenanceContractAsset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Service Contract Assets';

    protected static string|\UnitEnum|null $navigationGroup = 'Service Contracts';

    protected static ?string $slug = 'service-contract-assets';

    public static function form(Schema $schema): Schema
    {
        return MaintenanceContractAssetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MaintenanceContractAssetInfolist::configure($schema);
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
            'view' => ViewMaintenanceContractAsset::route('/{record}'),
            'edit' => EditMaintenanceContractAsset::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof MaintenanceContractAsset) {
            return static::getModelLabel();
        }

        $contract = $record->maintenanceContract?->contract_no ?? 'Unknown Contract';
        $asset = $record->fixedAsset?->fa_no ?? 'Unknown Asset';

        return "{$contract} - {$asset}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var MaintenanceContractAsset $record */
        return [
            'Contract' => $record->maintenanceContract?->contract_no ?? '—',
            'Asset' => $record->fixedAsset?->fa_no ?? '—',
            'Serial' => $record->covered_serial_no ?? '—',
            'Limit' => $record->asset_specific_limit ? number_format((float) $record->asset_specific_limit, 2) : 'Unlimited',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['maintenanceContract', 'fixedAsset']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'maintenanceContract.contract_no',
            'maintenanceContract.description',
            'fixedAsset.fa_no',
            'fixedAsset.description',
            'covered_serial_no',
        ];
    }
}
