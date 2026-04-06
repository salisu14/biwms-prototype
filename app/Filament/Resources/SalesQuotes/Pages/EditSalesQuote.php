<?php

namespace App\Filament\Resources\SalesQuotes\Pages;

use App\Filament\Resources\SalesQuotes\SalesQuoteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesQuote extends EditRecord
{
    protected static string $resource = SalesQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
