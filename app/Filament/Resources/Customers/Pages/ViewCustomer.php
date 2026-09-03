<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Pages\Finance\CustomerSettlementHistory;
use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\SubledgerOpeningBalances\SubledgerOpeningBalanceResource;
use App\Models\SubledgerOpeningBalance;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function getHeading(): string
    {
        return 'Customer No. '.($this->record->customer_number ?? '—')
            .' • Scope '.($this->record->name ?? '—')
            .' • Attribute '.($this->record->group?->code
                ? "{$this->record->group->code} - {$this->record->group->name}"
                : 'No Group');
    }

    public function getSubheading(): string
    {
        return 'No. '.($this->record->customer_number ?? '—')
            .' • Scope '.($this->record->name ?? '—')
            .' • Attribute '.($this->record->blocked ? 'Blocked' : 'Active');
    }

    public function getBreadcrumb(): string
    {
        return ($this->record->customer_number ?? '—')
            .' - '.($this->record->name ?? '—');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enterOpeningBalance')
                ->label('Enter Opening Balance')
                ->icon('heroicon-o-scale')
                ->color('warning')
                ->visible(fn (): bool => $this->openingBalanceForActiveBusiness() === null
                    && auth()->user()?->can('createCustomer', SubledgerOpeningBalance::class) === true)
                ->url(fn (): string => SubledgerOpeningBalanceResource::getUrl(
                    'create',
                    panel: 'admin',
                    parameters: [
                        'party_type' => 'CUSTOMER',
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
            Action::make('viewSubledger')
                ->label('View Subledger')
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->url(fn () => CustomerSubledgerSummary::getUrl([
                    'customerId' => $this->record->id,
                ])),
            Action::make('viewSettlementHistory')
                ->label('Settlement History')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('finance.customer_settlement_history.view') === true)
                ->url(fn () => CustomerSettlementHistory::urlForCurrentPanel([
                    'customer_id' => $this->record->id,
                ])),
            EditAction::make(),
        ];
    }

    private function openingBalanceForActiveBusiness(): ?SubledgerOpeningBalance
    {
        $businessId = (int) session('active_business_id', 0);
        $query = SubledgerOpeningBalance::query()
            ->where('business_id', $businessId)
            ->where('party_type', 'CUSTOMER')
            ->where('customer_id', $this->getRecord()->getKey())
            ->whereIn('status', [SubledgerOpeningBalance::STATUS_DRAFT, SubledgerOpeningBalance::STATUS_POSTED]);

        if ($businessId > 0) {
            return $query->latest('id')->first();
        }

        $matches = SubledgerOpeningBalance::query()
            ->where('party_type', 'CUSTOMER')
            ->where('customer_id', $this->getRecord()->getKey())
            ->whereIn('status', [SubledgerOpeningBalance::STATUS_DRAFT, SubledgerOpeningBalance::STATUS_POSTED])
            ->get(['id', 'business_id']);

        return $matches->pluck('business_id')->unique()->count() === 1
            ? SubledgerOpeningBalance::query()->find($matches->first()->id)
            : null;
    }
}
