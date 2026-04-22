<?php

namespace App\Filament\Resources\PaymentJournalLines\Pages;

use App\Filament\Resources\PaymentJournalLines\PaymentJournalLineResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentJournalLine extends EditRecord
{
    protected static string $resource = PaymentJournalLineResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}
