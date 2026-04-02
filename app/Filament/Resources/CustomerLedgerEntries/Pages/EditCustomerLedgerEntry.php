<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Pages;

use App\Filament\Resources\CustomerLedgerEntries\CustomerLedgerEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerLedgerEntry extends EditRecord
{
    protected static string $resource = CustomerLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
