<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

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
            EditAction::make(),
        ];
    }
}
