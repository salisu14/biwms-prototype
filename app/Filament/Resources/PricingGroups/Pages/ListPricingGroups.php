<?php

namespace App\Filament\Resources\PricingGroups\Pages;

use App\Filament\Resources\PricingGroups\PricingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPricingGroups extends ListRecords
{
    protected static string $resource = PricingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
