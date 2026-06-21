<?php

namespace App\Filament\Resources\CashReceiptLines\Pages;

use App\Filament\Resources\CashReceiptLines\CashReceiptLineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCashReceiptLine extends CreateRecord
{
    protected static string $resource = CashReceiptLineResource::class;
}
