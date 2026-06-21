<?php

namespace App\Filament\Resources\VendorInvoices\Pages;

use App\Filament\Resources\VendorInvoices\VendorInvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Number;

class EditVendorInvoice extends EditRecord
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
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
