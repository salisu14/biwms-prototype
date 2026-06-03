<?php

namespace App\Filament\Resources\VendorLedgerEntries\Pages;

use App\Filament\Resources\VendorLedgerEntries\VendorLedgerEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVendorLedgerEntry extends EditRecord
{
    protected static string $resource = VendorLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
