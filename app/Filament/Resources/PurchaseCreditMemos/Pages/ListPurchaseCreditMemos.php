<?php

namespace App\Filament\Resources\PurchaseCreditMemos\Pages;

use App\Filament\Resources\PurchaseCreditMemos\PurchaseCreditMemoResource;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseCreditMemos extends ListRecords
{
    protected static string $resource = PurchaseCreditMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
