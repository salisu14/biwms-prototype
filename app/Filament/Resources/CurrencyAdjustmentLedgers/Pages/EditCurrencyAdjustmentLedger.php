<?php

namespace App\Filament\Resources\CurrencyAdjustmentLedgers\Pages;

use App\Filament\Resources\CurrencyAdjustmentLedgers\CurrencyAdjustmentLedgerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCurrencyAdjustmentLedger extends EditRecord
{
    protected static string $resource = CurrencyAdjustmentLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
