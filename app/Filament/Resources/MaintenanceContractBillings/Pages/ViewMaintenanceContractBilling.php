<?php

namespace App\Filament\Resources\MaintenanceContractBillings\Pages;

use App\Filament\Resources\MaintenanceContractBillings\MaintenanceContractBillingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMaintenanceContractBilling extends ViewRecord
{
    protected static string $resource = MaintenanceContractBillingResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->maintenanceContract?->contract_no ?? 'Billing')
            .' • Scope '.($record->billing_date?->format('d/m/Y') ?? '—')
            .' • Attribute '.number_format((float) $record->amount, 2);
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->maintenanceContract?->description ?? 'Unknown Contract')
            .' • '.ucfirst((string) $record->status)
            .' • '.($record->purchaseInvoice?->document_number ?? 'No invoice');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->maintenanceContract
            ? "{$record->maintenanceContract->contract_no} - {$record->billing_date?->format('d/m/Y')}"
            : 'Maintenance Contract Billing';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
