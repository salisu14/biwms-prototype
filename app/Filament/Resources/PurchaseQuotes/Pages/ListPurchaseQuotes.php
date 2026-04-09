<?php

namespace App\Filament\Resources\PurchaseQuotes\Pages;

use App\Filament\Resources\PurchaseQuotes\PurchaseQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseQuotes extends ListRecords
{
    protected static string $resource = PurchaseQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
