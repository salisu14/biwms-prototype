<?php

namespace App\Filament\Resources\VendorLedgerEntries\Pages;

use App\Filament\Resources\SubledgerOpeningBalances\SubledgerOpeningBalanceResource;
use App\Filament\Resources\VendorLedgerEntries\VendorLedgerEntryResource;
use App\Models\CompanyInformation;
use App\Models\SubledgerOpeningBalance;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Number;

class ViewVendorLedgerEntry extends ViewRecord
{
    protected static string $resource = VendorLedgerEntryResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $vendorCode = $record->vendor?->vendor_code ?? 'Vendor';
        $amount = Number::currency((float) $record->amount, static::baseCurrencyCode($record->business_id));

        return $record->entry_number
            .' • '.$vendorCode
            .' • '.$amount;
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->vendor?->vendor_name ?? 'Unknown Vendor')
            .' • '.($record->document_type ?? 'Entry')
            .' • '.($record->document_number ?? 'No document');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->entry_number ?? 'Vendor Ledger Entry';
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
            ->where('party_type', 'VENDOR')
            ->where('vendor_id', $record->vendor_id)
            ->where('business_id', $record->business_id)
            ->first();
    }

    private static function baseCurrencyCode(?int $businessId): string
    {
        return (string) (CompanyInformation::query()
            ->where('business_id', $businessId ?: (int) session('active_business_id', 0))
            ->value('base_currency_code') ?: config('app.base_currency', 'NGN'));
    }
}
