<?php

namespace App\Filament\Resources\MaintenanceContractAssets\Pages;

use App\Filament\Resources\MaintenanceContractAssets\MaintenanceContractAssetResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMaintenanceContractAsset extends ViewRecord
{
    protected static string $resource = MaintenanceContractAssetResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->maintenanceContract?->contract_no ?? 'Maintenance Contract Asset')
            .' • Scope '.($record->fixedAsset?->fa_no ?? 'Unknown Asset')
            .' • Attribute '.($record->covered_serial_no ?: 'No serial');
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->fixedAsset?->description ?? 'No asset description')
            .' • '.($record->asset_specific_limit ? number_format((float) $record->asset_specific_limit, 2) : 'Unlimited')
            .' • '.($record->special_conditions ? 'Has conditions' : 'No special conditions');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->maintenanceContract
            ? "{$record->maintenanceContract->contract_no} - {$record->fixedAsset?->fa_no}"
            : 'Maintenance Contract Asset';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
