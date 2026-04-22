<?php

namespace App\Filament\Resources\PaymentJournalLines\Pages;

use App\Filament\Resources\PaymentJournalLines\PaymentJournalLineResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentJournalLine extends CreateRecord
{
    protected static string $resource = PaymentJournalLineResource::class;
}
