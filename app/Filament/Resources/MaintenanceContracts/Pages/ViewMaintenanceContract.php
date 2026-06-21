<?php

namespace App\Filament\Resources\MaintenanceContracts\Pages;

use App\Filament\Resources\MaintenanceContracts\MaintenanceContractResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMaintenanceContract extends ViewRecord
{
    protected static string $resource = MaintenanceContractResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->contract_no ?? 'Maintenance Contract')
            .' • Scope '.($record->vendor?->vendor_name ?? 'Unknown Vendor')
            .' • Attribute '.($record->contract_type?->value ?? '—');
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->description ?? 'No description')
            .' • '.($record->status?->value ?? 'Unknown Status')
            .' • '.number_format((float) $record->contract_value, 2).' '.($record->currency_code ?: 'NGN');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->contract_no.' - '.($record->description ?? 'Maintenance Contract');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
