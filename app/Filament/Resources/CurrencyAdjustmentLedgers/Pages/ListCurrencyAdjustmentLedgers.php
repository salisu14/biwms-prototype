<?php

namespace App\Filament\Resources\CurrencyAdjustmentLedgers\Pages;

use App\Filament\Resources\CurrencyAdjustmentLedgers\CurrencyAdjustmentLedgerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCurrencyAdjustmentLedgers extends ListRecords
{
    protected static string $resource = CurrencyAdjustmentLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
