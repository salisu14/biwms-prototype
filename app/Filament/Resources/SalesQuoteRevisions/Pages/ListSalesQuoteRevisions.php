<?php

namespace App\Filament\Resources\SalesQuoteRevisions\Pages;

use App\Filament\Resources\SalesQuoteRevisions\SalesQuoteRevisionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesQuoteRevisions extends ListRecords
{
    protected static string $resource = SalesQuoteRevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
