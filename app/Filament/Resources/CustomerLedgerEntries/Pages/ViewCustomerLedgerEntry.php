<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Pages;

use App\Filament\Resources\CustomerLedgerEntries\CustomerLedgerEntryResource;
use App\Filament\Resources\SubledgerOpeningBalances\SubledgerOpeningBalanceResource;
use App\Models\SubledgerOpeningBalance;
use Filament\Actions\Action;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOpeningBalance')
                ->label('View Opening Balance')
                ->icon('heroicon-o-document-magnifying-glass')
                ->visible(fn (): bool => ($source = $this->openingBalanceSource()) !== null
                    && auth()->user()?->can('view', $source) === true)
                ->url(fn (): string => SubledgerOpeningBalanceResource::getUrl('view', [
                    'record' => $this->openingBalanceSource(),
                ])),
        ];
    }

    private function openingBalanceSource(): ?SubledgerOpeningBalance
    {
        $record = $this->getRecord();

        if ($record->document_type !== 'OPENING_BALANCE' || $record->source_type !== SubledgerOpeningBalance::class) {
            return null;
        }

        return SubledgerOpeningBalance::query()
            ->whereKey($record->source_id)
            ->where('party_type', 'CUSTOMER')
            ->where('customer_id', $record->customer_id)
            ->where('business_id', $record->business_id)
            ->first();
    }
}
