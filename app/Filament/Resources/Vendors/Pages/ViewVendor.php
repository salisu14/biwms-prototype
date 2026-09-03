<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\SubledgerOpeningBalances\SubledgerOpeningBalanceResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\SubledgerOpeningBalance;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendor extends ViewRecord
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enterOpeningBalance')
                ->label('Enter Opening Balance')
                ->icon('heroicon-o-scale')
                ->color('warning')
                ->visible(fn (): bool => $this->openingBalanceForActiveBusiness() === null
                    && auth()->user()?->can('createVendor', SubledgerOpeningBalance::class) === true)
                ->url(fn (): string => SubledgerOpeningBalanceResource::getUrl(
                    'create',
                    panel: 'admin',
                    parameters: [
                        'party_type' => 'VENDOR',
                        'party_id' => $this->record->getKey(),
                        'business_id' => session('active_business_id'),
                    ],
                )),
            Action::make('viewOpeningBalance')
                ->label(fn (): string => $this->openingBalanceForActiveBusiness()?->status === SubledgerOpeningBalance::STATUS_DRAFT
                    ? 'Continue Opening Balance'
                    : 'View Opening Balance')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->visible(fn (): bool => ($opening = $this->openingBalanceForActiveBusiness()) !== null
                    && auth()->user()?->can('view', $opening) === true)
                ->url(fn (): string => SubledgerOpeningBalanceResource::getUrl('view', [
                    'record' => $this->openingBalanceForActiveBusiness(),
                ])),
            EditAction::make(),
        ];
    }

    private function openingBalanceForActiveBusiness(): ?SubledgerOpeningBalance
    {
        $businessId = (int) session('active_business_id', 0);
        $query = SubledgerOpeningBalance::query()
            ->where('business_id', $businessId)
            ->where('party_type', 'VENDOR')
            ->where('vendor_id', $this->getRecord()->getKey())
            ->whereIn('status', [SubledgerOpeningBalance::STATUS_DRAFT, SubledgerOpeningBalance::STATUS_POSTED]);

        if ($businessId > 0) {
            return $query->latest('id')->first();
        }

        // Vendor records are not business-owned in the legacy schema. When
        // no active context is available, resolve only an unambiguous party
        // opening balance; never guess across multiple businesses.
        $matches = SubledgerOpeningBalance::query()
            ->where('party_type', 'VENDOR')
            ->where('vendor_id', $this->getRecord()->getKey())
            ->whereIn('status', [SubledgerOpeningBalance::STATUS_DRAFT, SubledgerOpeningBalance::STATUS_POSTED])
            ->get(['id', 'business_id']);

        return $matches->pluck('business_id')->unique()->count() === 1
            ? SubledgerOpeningBalance::query()->find($matches->first()->id)
            : null;
    }
}
