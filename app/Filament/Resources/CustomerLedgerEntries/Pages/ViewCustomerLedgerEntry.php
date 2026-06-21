<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Pages;

use App\Filament\Resources\CustomerLedgerEntries\CustomerLedgerEntryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerLedgerEntry extends ViewRecord
{
    protected static string $resource = CustomerLedgerEntryResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $customerNumber = $record->customer?->customer_number ?? 'Customer';

        return $record->entry_number
            .' • Customer '.$customerNumber
            .' • '.number_format((float) $record->amount, 2);
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->customer?->name ?? 'Unknown Customer')
            .' • '.($record->document_type ?? 'Entry')
            .' • '.($record->document_number ?? 'No document');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->entry_number ?? 'Customer Ledger Entry';
    }
}
