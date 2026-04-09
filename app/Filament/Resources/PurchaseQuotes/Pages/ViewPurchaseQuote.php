<?php

namespace App\Filament\Resources\PurchaseQuotes\Pages;

use App\Filament\Resources\PurchaseQuotes\PurchaseQuoteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseQuote extends ViewRecord
{
    protected static string $resource = PurchaseQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
