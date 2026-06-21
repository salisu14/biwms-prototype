<?php

namespace App\Filament\Resources\CashReceiptLines\Pages;

use App\Filament\Resources\CashReceiptLines\CashReceiptLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashReceiptLines extends ListRecords
{
    protected static string $resource = CashReceiptLineResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New Receipt')];
    }
}
