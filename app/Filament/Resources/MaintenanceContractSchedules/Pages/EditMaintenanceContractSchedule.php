<?php

namespace App\Filament\Resources\MaintenanceContractSchedules\Pages;

use App\Filament\Resources\MaintenanceContractSchedules\MaintenanceContractScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceContractSchedule extends EditRecord
{
    protected static string $resource = MaintenanceContractScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
