<?php

namespace App\Filament\Resources\VendorLedgerEntries\Pages;

use App\Filament\Resources\VendorLedgerEntries\VendorLedgerEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorLedgerEntries extends ListRecords
{
    protected static string $resource = VendorLedgerEntryResource::class;

    protected static ?string $title = 'Vendor Ledger Entries';
}
