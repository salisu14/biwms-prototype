<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Pages;

use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Resources\CustomerLedgerEntries\CustomerLedgerEntryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCustomerLedgerEntries extends ListRecords
{
    protected static string $resource = CustomerLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('summaryView')
                ->label('Summary View')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(CustomerSubledgerSummary::getUrl()),
        ];
    }
}
