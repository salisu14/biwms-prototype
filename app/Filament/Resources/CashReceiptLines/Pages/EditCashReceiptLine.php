<?php

namespace App\Filament\Resources\CashReceiptLines\Pages;

use App\Filament\Resources\CashReceiptLines\CashReceiptLineResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCashReceiptLine extends EditRecord
{
    protected static string $resource = CashReceiptLineResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}
