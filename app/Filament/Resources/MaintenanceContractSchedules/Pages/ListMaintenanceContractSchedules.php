<?php

namespace App\Filament\Resources\MaintenanceContractSchedules\Pages;

use App\Filament\Resources\MaintenanceContractSchedules\MaintenanceContractScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceContractSchedules extends ListRecords
{
    protected static string $resource = MaintenanceContractScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
