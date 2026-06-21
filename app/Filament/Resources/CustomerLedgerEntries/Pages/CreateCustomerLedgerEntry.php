<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Pages;

use App\Filament\Resources\CustomerLedgerEntries\CustomerLedgerEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerLedgerEntry extends CreateRecord
{
    protected static string $resource = CustomerLedgerEntryResource::class;
}
