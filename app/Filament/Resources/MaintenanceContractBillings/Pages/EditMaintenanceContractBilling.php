<?php

namespace App\Filament\Resources\MaintenanceContractBillings\Pages;

use App\Filament\Resources\MaintenanceContractBillings\MaintenanceContractBillingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceContractBilling extends EditRecord
{
    protected static string $resource = MaintenanceContractBillingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
