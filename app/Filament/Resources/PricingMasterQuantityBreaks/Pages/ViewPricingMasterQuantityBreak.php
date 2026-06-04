<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks\Pages;

use App\Filament\Resources\PricingMasterQuantityBreaks\PricingMasterQuantityBreakResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPricingMasterQuantityBreak extends ViewRecord
{
    protected static string $resource = PricingMasterQuantityBreakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
