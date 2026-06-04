<?php

namespace App\Filament\Resources\PurchasePrices\Pages;

use App\Filament\Resources\PurchasePrices\PurchasePriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchasePrices extends ListRecords
{
    protected static string $resource = PurchasePriceResource::class;

    public function getTitle(): string
    {
        return 'Purchase Prices';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
