<?php

namespace App\Filament\Resources\VendorLedgerEntries\Pages;

use App\Filament\Resources\VendorLedgerEntries\VendorLedgerEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorLedgerEntry extends CreateRecord
{
    protected static string $resource = VendorLedgerEntryResource::class;
}
