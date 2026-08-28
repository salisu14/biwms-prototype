<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Pages\Finance\CustomerSettlementHistory;
use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Resources\Customers\CustomerResource;
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
}
