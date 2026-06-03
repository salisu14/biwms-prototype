<?php

namespace App\Filament\Resources\CustomerPriceOverrides\Pages;

use App\Filament\Resources\CustomerPriceOverrides\CustomerPriceOverrideResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerPriceOverride extends CreateRecord
{
    protected static string $resource = CustomerPriceOverrideResource::class;
}
