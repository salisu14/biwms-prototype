<?php

namespace App\Filament\Resources\PricingGroups\Pages;

use App\Filament\Resources\PricingGroups\PricingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPricingGroup extends EditRecord
{
    protected static string $resource = PricingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
