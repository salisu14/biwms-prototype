<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Pages;

use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Resources\CustomerLedgerEntries\CustomerLedgerEntryResource;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCustomerLedgerEntries extends ListRecords
{
    protected static string $resource = CustomerLedgerEntryResource::class;

    protected static ?string $title = 'Customer Ledger Entries';

    public ?int $customerId = null;

    public ?Customer $customer = null;

    public function mount(): void
    {
        parent::mount();

        $this->customerId = request()->integer('customerId') ?: null;
        $this->customer = $this->customerId !== null
            ? Customer::query()->find($this->customerId)
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('summaryView')
                ->label('Summary View')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(CustomerSubledgerSummary::getUrl(array_filter([
                    'customerId' => $this->customerId,
                ]))),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if ($this->customerId !== null) {
            $query->where('customer_id', $this->customerId);
        }

        return $query;
    }
}
