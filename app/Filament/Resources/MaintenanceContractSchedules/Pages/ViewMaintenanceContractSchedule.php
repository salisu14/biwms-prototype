<?php

namespace App\Filament\Resources\MaintenanceContractSchedules\Pages;

use App\Filament\Resources\MaintenanceContractSchedules\MaintenanceContractScheduleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMaintenanceContractSchedule extends ViewRecord
{
    protected static string $resource = MaintenanceContractScheduleResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->maintenanceContract?->contract_no ?? 'Maintenance Dispatch')
            .' • Scope '.($record->fixedAsset?->fa_no ?? 'Unassigned Asset')
            .' • Attribute '.($record->frequency ? str($record->frequency)->replace('_', ' ')->title()->toString() : '—');
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->service_description ?? 'No service description')
            .' • '.($record->next_service_date?->format('d/m/Y') ?? 'No next service date')
            .' • '.($record->is_active ? 'Active' : 'Inactive');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->maintenanceContract
            ? "{$record->maintenanceContract->contract_no} - ".($record->fixedAsset?->fa_no ?? 'Dispatch')
            : 'Maintenance Contract Schedule';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
