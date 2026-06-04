<?php

namespace App\Filament\Resources\MaintenanceContractAssets\Pages;

use App\Filament\Resources\MaintenanceContractAssets\MaintenanceContractAssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceContractAssets extends ListRecords
{
    protected static string $resource = MaintenanceContractAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
