<?php

namespace App\Filament\Resources\VendorLedgerEntries\Pages;

use App\Filament\Resources\VendorLedgerEntries\VendorLedgerEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorLedgerEntry extends ViewRecord
{
    protected static string $resource = VendorLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
