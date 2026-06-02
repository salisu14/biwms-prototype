<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
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
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
