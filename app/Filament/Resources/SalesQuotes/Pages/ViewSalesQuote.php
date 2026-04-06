<?php

namespace App\Filament\Resources\SalesQuotes\Pages;

use App\Filament\Resources\SalesQuotes\SalesQuoteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesQuote extends ViewRecord
{
    protected static string $resource = SalesQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
