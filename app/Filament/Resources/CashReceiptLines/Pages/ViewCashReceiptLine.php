<?php

namespace App\Filament\Resources\CashReceiptLines\Pages;

use App\Filament\Resources\CashReceiptLines\CashReceiptLineResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCashReceiptLine extends ViewRecord
{
    protected static string $resource = CashReceiptLineResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
