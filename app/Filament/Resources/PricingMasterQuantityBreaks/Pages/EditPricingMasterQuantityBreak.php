<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks\Pages;

use App\Filament\Resources\PricingMasterQuantityBreaks\PricingMasterQuantityBreakResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPricingMasterQuantityBreak extends EditRecord
{
    protected static string $resource = PricingMasterQuantityBreakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
