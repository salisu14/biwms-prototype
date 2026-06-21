<?php

namespace App\Filament\Sales\Resources\SalesQuotes\Pages;

use App\Filament\Sales\Resources\SalesQuotes\SalesQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesQuotes extends ListRecords
{
    protected static string $resource = SalesQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
