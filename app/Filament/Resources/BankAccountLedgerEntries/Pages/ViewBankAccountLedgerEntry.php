<?php

namespace App\Filament\Resources\BankAccountLedgerEntries\Pages;

use App\Filament\Resources\BankAccountLedgerEntries\BankAccountLedgerEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBankAccountLedgerEntry extends ViewRecord
{
    protected static string $resource = BankAccountLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
