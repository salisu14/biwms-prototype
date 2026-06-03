<?php

namespace App\Filament\Resources\PricingMasters\Pages;

use App\Filament\Resources\PricingMasters\PricingMasterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPricingMasters extends ListRecords
{
    protected static string $resource = PricingMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
