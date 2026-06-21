<?php

namespace App\Filament\Resources\MaintenanceContractAssets\Pages;

use App\Filament\Resources\MaintenanceContractAssets\MaintenanceContractAssetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceContractAsset extends EditRecord
{
    protected static string $resource = MaintenanceContractAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
