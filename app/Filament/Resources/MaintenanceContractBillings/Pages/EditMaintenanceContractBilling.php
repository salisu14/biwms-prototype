<?php

namespace App\Filament\Resources\MaintenanceContractBillings\Pages;

use App\Filament\Resources\MaintenanceContractBillings\MaintenanceContractBillingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Number;

class EditMaintenanceContractBilling extends EditRecord
{
    protected static string $resource = MaintenanceContractBillingResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $currencyCode = $record->maintenanceContract?->currency_code ?? config('app.default_currency', 'NGN');

        return 'billing • '.($record->maintenanceContract?->contract_no ?? '—')
            .' • '.Number::currency((float) $record->amount, $currencyCode);
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return 'invoice • '.($record->purchaseInvoice?->document_number ?? '—')
            .' • status • '.ucfirst((string) $record->status)
            .' • billed • '.($record->billing_date?->format('d/m/Y') ?? '—');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->maintenanceContract
            ? "{$record->maintenanceContract->contract_no} - ".($record->billing_date?->format('d/m/Y') ?? 'Billing')
            : 'Maintenance Contract Billing';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
