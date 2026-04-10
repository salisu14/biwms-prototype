<?php

namespace App\Filament\Resources\CurrencyAdjustmentLedgers\Pages;

use App\Filament\Resources\CurrencyAdjustmentLedgers\CurrencyAdjustmentLedgerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCurrencyAdjustmentLedger extends ViewRecord
{
    protected static string $resource = CurrencyAdjustmentLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
