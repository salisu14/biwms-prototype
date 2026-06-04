<?php

namespace App\Filament\Resources\MaintenanceContractBillings\Pages;

use App\Filament\Resources\MaintenanceContractBillings\MaintenanceContractBillingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceContractBillings extends ListRecords
{
    protected static string $resource = MaintenanceContractBillingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
