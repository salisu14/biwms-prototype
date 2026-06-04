<?php

namespace App\Filament\Resources\VendorLedgerEntries\Pages;

use App\Filament\Resources\VendorLedgerEntries\VendorLedgerEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Number;

class EditVendorLedgerEntry extends EditRecord
{
    protected static string $resource = VendorLedgerEntryResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $vendorCode = $record->vendor?->vendor_code ?? 'Vendor';
        $amount = Number::currency((float) $record->amount, $record->currency_code ?? config('app.default_currency', 'USD'));

        return $record->entry_number
            .' • '.$vendorCode
            .' • '.$amount;
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->vendor?->vendor_name ?? 'Unknown Vendor')
            .' • '.($record->document_type ?? 'Entry')
            .' • '.($record->document_number ?? 'No document');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->entry_number ?? 'Vendor Ledger Entry';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
