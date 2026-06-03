<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks\Pages;

use App\Filament\Resources\PricingMasterQuantityBreaks\PricingMasterQuantityBreakResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPricingMasterQuantityBreaks extends ListRecords
{
    protected static string $resource = PricingMasterQuantityBreakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
