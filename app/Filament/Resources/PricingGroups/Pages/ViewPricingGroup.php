<?php

namespace App\Filament\Resources\PricingGroups\Pages;

use App\Filament\Resources\PricingGroups\PricingGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPricingGroup extends ViewRecord
{
    protected static string $resource = PricingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
