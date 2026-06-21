<?php

namespace App\Filament\Resources\VendorInvoices\Pages;

use App\Filament\Resources\VendorInvoices\VendorInvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Number;

class ViewVendorInvoice extends ViewRecord
{
    protected static string $resource = VendorInvoiceResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $amount = Number::currency((float) $record->amount_including_tax, $record->currency_code ?: config('app.default_currency', 'USD'));

        return ($record->document_number ?? 'Vendor Invoice')
            .' • '.($record->vendor?->vendor_code ?? 'Vendor')
            .' • '.$amount;
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return trim(implode(' • ', array_filter([
            $record->vendor?->vendor_name,
            $record->vendor_invoice_no,
            $record->status,
        ])));
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->document_number ?? 'Vendor Invoice';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
