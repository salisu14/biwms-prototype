<?php

namespace App\Filament\Resources\CustomerPriceOverrides\Pages;

use App\Filament\Resources\CustomerPriceOverrides\CustomerPriceOverrideResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerPriceOverride extends EditRecord
{
    protected static string $resource = CustomerPriceOverrideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
