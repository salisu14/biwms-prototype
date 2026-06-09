<?php

namespace App\Filament\Resources\BankAccountLedgerEntries\Pages;

use App\Filament\Resources\BankAccountLedgerEntries\BankAccountLedgerEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankAccountLedgerEntries extends ListRecords
{
    protected static string $resource = BankAccountLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
