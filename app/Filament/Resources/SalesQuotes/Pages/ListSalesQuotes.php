<?php

namespace App\Filament\Resources\SalesQuotes\Pages;

use App\Filament\Resources\SalesQuotes\SalesQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesQuotes extends ListRecords
{
    protected static string $resource = SalesQuoteResource::class;

    protected static ?string $title = 'Sales Quotes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
