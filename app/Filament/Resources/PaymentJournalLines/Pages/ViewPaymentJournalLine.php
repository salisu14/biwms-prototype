<?php

namespace App\Filament\Resources\PaymentJournalLines\Pages;

use App\Filament\Resources\PaymentJournalLines\PaymentJournalLineResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentJournalLine extends ViewRecord
{
    protected static string $resource = PaymentJournalLineResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
