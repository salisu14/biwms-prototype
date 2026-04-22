<?php

namespace App\Filament\Resources\PaymentJournalLines\Pages;

use App\Filament\Resources\PaymentJournalLines\PaymentJournalLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentJournalLines extends ListRecords
{
    protected static string $resource = PaymentJournalLineResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New Payment')];
    }
}
