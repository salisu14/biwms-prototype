<?php

namespace App\Filament\Resources\CustomerPriceOverrides\Pages;

use App\Filament\Resources\CustomerPriceOverrides\CustomerPriceOverrideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerPriceOverrides extends ListRecords
{
    protected static string $resource = CustomerPriceOverrideResource::class;

    protected static ?string $title = 'Customer Price Overrides';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
