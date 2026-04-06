<?php

namespace App\Filament\Resources\SalesCreditMemos\Pages;

use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesCreditMemos extends ListRecords
{
    protected static string $resource = SalesCreditMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
