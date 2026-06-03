<?php

namespace App\Filament\Resources\PricingMasters\Pages;

use App\Filament\Resources\PricingMasters\PricingMasterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPricingMaster extends ViewRecord
{
    protected static string $resource = PricingMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
